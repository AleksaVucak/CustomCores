# CustomCore — Flash messages (Commit 1.8)

Flash messages tell the user about the result of an action after a redirect.

## Types

- `success`
- `warning`
- `error`

## How it works

1. On request A, call `customcore_flash_set()` (or the success/warning/error helpers).
2. Redirect with `customcore_redirect('somewhere.php')`.
3. On request B, `includes/header.php` renders the message once and clears it.

Messages are stored in the PHP session and intentionally last for **one redirect only**.

## Example

```php
require_once __DIR__ . '/includes/flash.php';

customcore_flash_success('Your account was created. Please log in.');
customcore_redirect('login.php');
```

## Files

| File | Role |
| ---- | ---- |
| `includes/flash.php` | Queue, peek, and render helpers |
| `includes/functions.php` | `customcore_session_start()`, `customcore_redirect()` |
| `includes/header.php` | Calls `customcore_flash_render()` on every layout page |
| `assets/css/main.css` | `.flash`, `.flash--success|warning|error`, `.flash-stack` |

## Manual test

1. Temporarily in `index.php` (above the header include):

```php
require_once __DIR__ . '/includes/flash.php';
customcore_flash_warning('Flash test message');
customcore_redirect('about.php');
```

2. Load `index.php` in the browser — you should land on About and see the warning once.
3. Refresh About — the message must be gone.
4. Remove the temporary lines when finished.
