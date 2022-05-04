<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Service;

interface ProjectBuilderInterface
{
    public function generate(): void;
}
