# API de Reservas de Salas de Reunião

API REST em Laravel para cadastrar salas e gerenciar reservas — incluindo reservas
recorrentes, checagem de conflito de horários e cancelamento com trilha de auditoria.

- **Laravel** 13 · **PHP** 8.4 · **PostgreSQL** 17
- Autenticação por token (**Laravel Sanctum**)
- Suíte de testes em **Pest** (51 testes), análise estática **PHPStan/Larastan**, estilo **Pint**

---

## Como rodar do zero

Pré-requisitos: PHP 8.4, Composer e PostgreSQL 17 (com a extensão `btree_gist`
disponível — vem por padrão no pacote `postgresql-contrib`).

```bash
# 1. Dependências
composer install

# 2. Ambiente
cp .env.example .env
php artisan key:generate

# 3. Banco — ajuste DB_USERNAME / DB_PASSWORD no .env e crie a base
createdb reservas_salas          # ou: psql -c "CREATE DATABASE reservas_salas;"

# 4. Estrutura + dados de exemplo
php artisan migrate --seed

# 5. Subir a API
php artisan serve                # http://localhost:8000
```

### Rodar os testes

```bash
php artisan test
```

Os testes usam **SQLite em memória** por padrão (zero configuração). A pipeline de
CI (`.github/workflows/ci.yml`) roda a mesma suíte contra **PostgreSQL**, para
exercitar também a *exclusion constraint* descrita abaixo.

### Usuários criados pelo seeder

| e-mail              | senha      | papel  |
| ------------------- | ---------- | ------ |
| `admin@example.com` | `password` | admin  |
| `user@example.com`  | `password` | user   |

---

## Testar com Postman

Importe [`docs/reservas-salas.postman_collection.json`](docs/reservas-salas.postman_collection.json)
no Postman. Com a API rodando (`php artisan migrate:fresh --seed` + `php artisan serve`),
rode **Auth > Login (admin)** (salva o token sozinho) e depois as pastas **Salas** e
**Reservas** — ou use o *Collection Runner* para rodar a coleção inteira em ordem. Cada
request já traz asserções (`Tests`) cobrindo os cenários do enunciado: conflito de
horário, reservas encostadas, recorrência, cancelamento e controle de acesso.

## Autenticação

```bash
# Registrar (retorna o token) ou logar
curl -X POST http://localhost:8000/api/auth/login \
  -H "Accept: application/json" \
  -d email=admin@example.com -d password=password

# Usar o token nas demais rotas
curl http://localhost:8000/api/rooms \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

---

## Endpoints

Todas as rotas abaixo (exceto `register`/`login`) exigem
`Authorization: Bearer <token>`.

| Método | Rota                                 | Acesso            | Descrição |
| ------ | ------------------------------------ | ----------------- | --------- |
| POST   | `/api/auth/register`                 | público           | Cria usuário, devolve token |
| POST   | `/api/auth/login`                    | público           | Autentica, devolve token (rate-limit 6/min) |
| POST   | `/api/auth/logout`                   | autenticado       | Revoga o token atual |
| GET    | `/api/auth/me`                       | autenticado       | Dados do usuário logado |
| GET    | `/api/rooms`                         | autenticado       | Lista salas (`?search=`, `?min_capacity=`) |
| POST   | `/api/rooms`                         | **admin**         | Cria sala (`name`, `capacity`) |
| GET    | `/api/rooms/{room}`                  | autenticado       | Detalhe da sala |
| PATCH  | `/api/rooms/{room}`                  | **admin**         | Atualiza sala |
| DELETE | `/api/rooms/{room}`                  | **admin**         | Remove sala (soft delete; 409 se houver reserva futura) |
| GET    | `/api/reservations`                  | autenticado       | Lista reservas (filtros abaixo) |
| POST   | `/api/reservations`                  | autenticado       | Cria reserva avulsa ou recorrente |
| GET    | `/api/reservations/{reservation}`    | autenticado       | Detalhe da reserva |
| POST   | `/api/reservations/{reservation}/cancel` | responsável ou **admin** | Cancela (nunca apaga) |

**Filtros de `GET /api/reservations`:** `room_id`, `date=YYYY-MM-DD` (reservas que
cruzam aquele dia), `from` / `to` (intervalo de datas), `status`, `mine=true`,
`per_page`.

### Criar uma reserva

```jsonc
POST /api/reservations
{
  "room_id": 1,
  "starts_at": "2026-09-07T14:00:00-03:00",   // ISO-8601, com offset
  "ends_at":   "2026-09-07T15:00:00-03:00",
  "user_id": 5,                                // opcional; só admin pode reservar por outro
  "recurrence": {                              // opcional
    "frequency": "weekly",                     // "weekly" | "daily"
    "interval": 1,                             // a cada N semanas/dias (padrão 1)
    "count": 4                                 // nº de ocorrências
  }
}
```

Resposta de conflito (`422`):

```jsonc
{
  "message": "The requested time slot is not available for this room.",
  "errors": { "starts_at": ["The requested time slot is not available for this room."] },
  "conflicts": [
    {
      "requested_starts_at": "2026-09-06T14:30:00+00:00",
      "requested_ends_at": "2026-09-06T15:30:00+00:00",
      "conflicting_reservation_id": 3,
      "conflicting_starts_at": "2026-09-06T14:00:00+00:00",
      "conflicting_ends_at": "2026-09-06T15:00:00+00:00"
    }
  ]
}
```

### Cancelar

```jsonc
POST /api/reservations/{id}/cancel
{
  "scope": "occurrence",   // "occurrence" (padrão) | "series"
  "reason": "Reunião adiada"
}
```

---

## Decisões de modelagem

### Tabelas

| Tabela                | Papel |
| --------------------- | ----- |
| `users`               | Autenticação + coluna `role` (`admin` / `user`). `role` fora do mass-assignment. |
| `rooms`               | `name` (único), `capacity` (`smallint`), *soft delete*. |
| `reservation_series`  | O **padrão** de uma reserva recorrente (frequência, intervalo, nº de ocorrências, janela da 1ª ocorrência). |
| `reservations`        | Uma **ocorrência concreta** — é a fonte da verdade para a checagem de conflito. |

`reservations` guarda `room_id`, `user_id` (responsável), `reservation_series_id`
(nulo quando avulsa), `starts_at` / `ends_at` (`timestamptz`), `status`
(`confirmed` / `cancelled`) e os campos de auditoria de cancelamento
(`cancelled_at`, `cancelled_by`, `cancellation_reason`).

Índices: `(room_id, starts_at, ends_at)` e `(room_id, status)` para a busca de
conflito; no PostgreSQL há ainda um índice **parcial** só sobre reservas
confirmadas.

### Recorrência: ocorrências materializadas

Quando uma reserva é recorrente, o padrão é gravado em `reservation_series` **e**
uma linha por ocorrência é criada em `reservations`, todas apontando para a série.

Optei por materializar (em vez de guardar só a regra e expandir em tempo de
consulta) porque isso torna a checagem de conflito uma simples comparação de
intervalos contra linhas reais — sem precisar expandir RRULEs a cada query. O
custo é que a recorrência precisa ser **limitada**: `count` é obrigatório e há um
teto configurável (`config/reservations.php` → `max_occurrences`, padrão 52).

Recorrência mensal não foi implementada — só `daily` e `weekly`, que cobrem o
exemplo do enunciado. Adicionar `monthly` é só um novo `case` no enum
`RecurrenceFrequency`.

### Regra de sobreposição

Os intervalos são tratados como **semiabertos** `[início, fim)`. Duas reservas da
mesma sala, ambas `confirmed`, colidem quando:

```
existente.starts_at < nova.ends_at   AND   existente.ends_at > nova.starts_at
```

Essa única expressão cobre todos os casos de borda: sobreposição parcial à
esquerda ou à direita, uma reserva contida na outra (nos dois sentidos) e
intervalos idênticos. Reservas encostadas (uma termina exatamente quando a outra
começa) **não** colidem. A lógica isolada está em
[`TimeInterval::overlaps()`](app/Services/Reservations/TimeInterval.php) e é
coberta por um dataset dedicado em
[`tests/Unit/TimeIntervalTest.php`](tests/Unit/TimeIntervalTest.php).

Para uma reserva recorrente, **todas** as ocorrências são checadas antes de
qualquer escrita; se uma só conflita, nada é gravado (a transação inteira é
revertida) e a resposta lista as ocorrências problemáticas.

### Duas camadas de proteção contra conflito

1. **Aplicação** — a criação roda dentro de uma transação; as reservas
   confirmadas da sala na janela são lidas com `SELECT ... FOR UPDATE` antes da
   checagem, fechando a janela entre "verificar" e "inserir".
2. **Banco (PostgreSQL)** — uma *exclusion constraint* GiST
   (`reservations_no_overlap`) garante, no nível do schema, que nunca existam duas
   reservas confirmadas sobrepostas na mesma sala, mesmo sob concorrência real.
   Se essa constraint disparar, a API traduz o erro para o mesmo `422` de
   conflito.

   A constraint usa `tstzrange(starts_at, ends_at, '[)')` e um `WHERE status =
   'confirmed'` parcial. Só é criada no PostgreSQL; no SQLite (usado nos testes
   locais) a garantia fica com a camada de aplicação — a pipeline de CI roda os
   testes no PostgreSQL para cobrir também a constraint.

### Fuso horário

`config/app.php` mantém a aplicação em **UTC** e a conexão PostgreSQL fixa a
sessão em UTC (`config/database.php` → `connections.pgsql.timezone`), então
valores `timestamptz` fazem *round-trip* sem ambiguidade. O cliente envia
datas ISO-8601 **com offset** e a API compara tudo em UTC.

### Cancelamento

Cancelar nunca apaga a linha. O `status` vai para `cancelled` e são gravados
`cancelled_at`, `cancelled_by` e `cancellation_reason`.

- `scope: occurrence` (padrão) — cancela só aquela ocorrência.
- `scope: series` — cancela as ocorrências **futuras** da série; as que já
  aconteceram permanecem como estão.

Uso `POST /{id}/cancel` em vez de `DELETE` porque é uma transição de estado com
corpo (`scope`, `reason`), não a remoção de um recurso.

### Controle de acesso

- **Sanctum** para autenticação por token (`auth:sanctum` em todas as rotas de
  negócio).
- **Policies** para autorização:
  - [`ReservationPolicy::cancel`](app/Policies/ReservationPolicy.php) — só o
    responsável pela reserva ou um admin. Vale tanto para a ocorrência quanto
    para a série (todas as ocorrências de uma série têm o mesmo responsável).
  - [`RoomPolicy`](app/Policies/RoomPolicy.php) — leitura liberada para qualquer
    autenticado; escrita (criar/editar/remover sala) só para admin. O enunciado
    não define permissão de sala, então essa foi uma decisão minha.
- `user_id` de `Room` e o `role` de `User` ficam fora do `$fillable`; a coluna
  `user_id` de uma reserva vem do usuário autenticado (ou, para admin, de um
  `user_id` explícito e validado).

### O que ficou de fora (de propósito)

- **Editar** uma reserva: o fluxo é cancelar e criar de novo. Mudança de horário
  é, na prática, uma nova checagem de conflito.
- Recorrência mensal / dias-da-semana múltiplos (ex.: "seg e qua").
- Paginação por cursor — a listagem usa paginação simples do Laravel.

---

## Estrutura

```
app/
  Enums/                    UserRole, ReservationStatus, RecurrenceFrequency
  Models/                   User, Room, Reservation, ReservationSeries
  Http/
    Controllers/Api/        AuthController, RoomController, ReservationController
    Requests/Api/           Form Requests (validação + autorização)
    Resources/              Transformação das respostas JSON
  Policies/                 RoomPolicy, ReservationPolicy
  Services/Reservations/
    TimeInterval            Intervalo semiaberto + regra de sobreposição
    RecurrenceRule          DTO da recorrência
    RecurrenceExpander      Expande a regra em ocorrências
    ReservationConflictChecker   A query de conflito (com lock)
    ReservationBooker       Orquestra: expande → transação → checa → grava
    ReservationCanceller    Cancelamento de ocorrência / série
  Exceptions/               ReservationConflictException (render 422 + conflicts)
config/reservations.php     Limites (nº de ocorrências, duração, antecedência)
database/
  migrations/  factories/  seeders/
routes/api.php
tests/  Unit/ (regra pura)  ·  Feature/ (auth, rooms, reservations)
```

## Comandos úteis

```bash
composer test        # limpa config + roda a suíte
composer lint        # Pint (corrige o estilo)
composer stan        # PHPStan
composer ci          # lint:check + stan + test
```
