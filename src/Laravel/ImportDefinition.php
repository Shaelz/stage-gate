<?php

declare(strict_types=1);

namespace StageGate\Laravel;

use StageGate\FieldGroup;
use StageGate\Schema;

/**
 * The one piece of domain-specific configuration a host app supplies per
 * import type (e.g. "fixtures", "teams"). Job classes resolve this from the
 * container by class-string at handle() time, rather than holding it as job
 * state, so a Schema's validator closures never need to survive queue
 * serialization.
 */
interface ImportDefinition
{
    public function schema(): Schema;

    /** @return FieldGroup[] */
    public function fieldGroups(): array;

    public function existingRowsProvider(): ExistingRowsProvider;

    public function publishWriter(): PublishWriter;
}
