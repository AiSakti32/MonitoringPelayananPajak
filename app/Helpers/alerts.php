<?php

declare(strict_types=1);

function alert_count(): int
{
    return \App\Services\AlertService::countForCurrentUser();
}
