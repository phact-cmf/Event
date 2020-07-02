<?php declare(strict_types=1);

namespace Tests\Mocks;

class InvokableListener
{
    public function __invoke(SimpleEvent $event)
    {

    }
}
