<?php

declare(strict_types=1);

namespace StageGate;

final class PublishBlockedException extends \RuntimeException
{
    /** @param string[] $blockedRowKeys */
    public function __construct(public readonly array $blockedRowKeys)
    {
        parent::__construct(sprintf(
            'Cannot publish: %d overwrite-risk row(s) require approval.',
            count($blockedRowKeys),
        ));
    }
}
