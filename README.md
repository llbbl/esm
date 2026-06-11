# enum-state-machine

Zero-configuration, framework-agnostic PHP state machine driven by **Enums** and **Attributes**.

Instead of bloated configuration arrays, you declare transitions, guards, and side-effects directly on the enum that represents your state — so the enum *is* the documentation, fully typed and statically analyzable.

```php
use EnumStateMachine\Attributes\Transition;
use EnumStateMachine\Attributes\StateMachineConfig;

#[StateMachineConfig(dispatchEvents: true)]
#[Transition(to: self::Cancelled, guard: NotYetShippedGuard::class)] // wildcard: any state → Cancelled
enum OrderState: string
{
    case Pending = 'pending';

    #[Transition(to: self::Processing, after: ChargeCreditCardHook::class)]
    case Paid = 'paid';

    #[Transition(
        to: self::Shipped,
        guard: HasValidAddressGuard::class,
        before: ReserveInventoryHook::class,
        after: SendTrackingEmailHook::class,
    )]
    case Processing = 'processing';

    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
}
```

```php
use EnumStateMachine\StateMachine;
use EnumStateMachine\Exceptions\InvalidTransitionException;

// Construct from your model's enum-typed state (`$order->state` is typed
// `OrderState`) for full PHPStan narrowing of `transitionTo()` / `getCurrentState()`.
$machine = new StateMachine($order->state);

if ($machine->can(OrderState::Shipped, context: $order)) {
    // show the "Ship" button
}

try {
    $order->state = $machine->transitionTo(OrderState::Shipped, context: $order);
} catch (InvalidTransitionException $e) {
    // no such transition, or a guard rejected it
}
```

## Core principles

- **The enum is the truth** — transitions, guards, and side-effects live on the enum case.
- **Strictly typed** — full IDE autocompletion and PHPStan/Psalm support.
- **Agnostic** — vanilla PHP, Laravel, Symfony, anything. Optional PSR-11 container (DI for guards/hooks) and PSR-14 dispatcher (transition events); neither is required.

## Requirements

- PHP **>= 8.1** (Enums + Attributes)
- Optional: `psr/container` (resolve guards/hooks via DI), `psr/event-dispatcher` (emit transition events)

## Installation

```bash
composer require llbbl/enum-state-machine
```

## Concepts

| Piece | Role |
| --- | --- |
| `#[Transition(to, guard, before, after, includeSelf)]` | Declares an allowed transition. On a **case** = from that state; on the **enum class** = wildcard from any state. Repeatable. |
| `#[StateMachineConfig(dispatchEvents, event)]` | Class-level config (event dispatching toggle / custom event). |
| `GuardInterface` | `__invoke($from, $to, $context): bool` — vetoes a transition. Must be side-effect-free (also run by `can()`). |
| `StateHookInterface` | `__invoke($from, $to, $context): void` — `before`/`after` side-effects. |
| `StateTransitioned` | PSR-14 event emitted after a successful transition. |
| `StateMachine` | The engine: `can()`, `transitionTo()`, `getCurrentState()`. |

Order of a transition: **guards** → **before hooks** → state changes → **after hooks** → event. Guards/before-hook failures propagate raw and leave state unchanged; an after-hook failure leaves the state changed but throws `HookExecutionException`.

## Development setup

This repo uses [**mise**](https://mise.jdx.dev) to pin PHP and [**just**](https://github.com/casey/just) as the task runner.

### 1. Bootstrap the toolchain (macOS, one-time)

```bash
just php setup      # Homebrew C libs + builds the pinned PHP against openssl@3
just install        # composer install
```

The PHP toolchain recipes live in a `php` module (`just php <recipe>`):

| Recipe | Purpose |
| --- | --- |
| `just php setup` | One-time bootstrap (Homebrew libs + build the pinned PHP). |
| `just php version` | Print the active PHP version. |
| `just php latest` | Latest stable (non-RC) PHP vs the current pin. |
| `just php floor` | Oldest php.net **active-support** PHP — the compatibility floor. |
| `just php bump [VERSION]` | Repin mise.toml to the latest stable (or an explicit version); refuses RCs. |

`just php setup` compiles PHP from source via mise. Two macOS gotchas it handles for you:

- **openssl** — the mise php plugin hardcodes the EOL `openssl@1.1`, which yields a PHP with no working TLS wrapper (Composer-over-https breaks). The recipe points the build at `openssl@3` via `brew --prefix`.
- **link libs** — it installs `gd`, `icu4c`, `libzip`, `oniguruma`, `libxml2` (the libs the build force-links) so `./configure` doesn't abort. The state machine uses none of these at runtime; they're build-time link deps of the PHP binary.

> If the build stops at a `checking for … no` line for a different lib, install the matching Homebrew formula and re-run `just php setup`.

### 2. Everyday commands

```bash
just            # list recipes
just test       # run the Pest suite
just stan       # PHPStan at max level
just qa         # stan + test (the quality gate)
just coverage   # tests with coverage
```

All recipes run through the mise-pinned PHP.

### 3. Testing on older PHP (8.1–8.3)

The library supports PHP **>= 8.1**, but the dev toolchain doesn't: Pest 3 pulls
in `symfony/*` v8, which requires PHP **>= 8.4.1**. So the committed
`composer.lock` only installs on 8.4+, and GitHub CI runs the full suite on the
**currently-supported** versions (8.4 and 8.5) with a reproducible
`composer install`.

Older versions are exercised **locally via Docker**, where each version resolves
its own compatible dependency set:

```bash
just legacy all        # 8.1 (lint) + 8.2 + 8.3 (full Pest suite)
just legacy run 8.2    # a single version
just legacy build      # rebuild the images after editing docker/Dockerfile
```

- **8.2 / 8.3** run the full Pest suite — the container does a `composer update`
  (resolving `symfony` v7, which Pest 3 also supports) before testing.
- **8.1** can't install Pest 3 at all, so it only syntax-checks `src/` (`php -l`)
  to defend the runtime floor.

The repo is mounted **read-only** and copied inside the container, so a legacy
run's `composer update` never rewrites your 8.4-resolved host `composer.lock`.
Requires Docker (`docker compose`); the recipes live in the `legacy` module
(`compose.yaml` + `docker/`).

## License

MIT
