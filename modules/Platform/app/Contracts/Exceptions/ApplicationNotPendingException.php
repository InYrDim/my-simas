<?php

namespace Modules\Platform\App\Contracts\Exceptions;

/**
 * approve()/reject() was called on an application that is no longer
 * pending (already approved or rejected) — the decision guard that
 * keeps double processing impossible.
 */
final class ApplicationNotPendingException extends \RuntimeException {}
