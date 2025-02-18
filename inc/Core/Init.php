<?php

namespace AstraChild\Core;


class Init {
    public function __construct(private readonly array $services = []) {}

    public function initialize(): void {
        foreach ($this->services as $service) {
            if (method_exists($service, 'register')) {
                $service->register();
            }
        }
    }
}
