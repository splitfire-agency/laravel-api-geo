<?php

namespace Splitfire\ApiGeo\Tests;

use Illuminate\Foundation\Exceptions\Handler;
use Throwable;

final class RecordingHandler extends Handler
{
    /** @var list<Throwable> */
    public array $reported = [];

    public function report(Throwable $e): void
    {
        $this->reported[] = $e;
    }
}
