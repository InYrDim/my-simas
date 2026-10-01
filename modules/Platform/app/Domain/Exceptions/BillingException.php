<?php

namespace Modules\Platform\App\Domain\Exceptions;

/**
 * A billing rule was violated (unknown plan, wrong subscription state,
 * invoice not payable). Controllers turn it into a validation message.
 */
final class BillingException extends PlatformException {}
