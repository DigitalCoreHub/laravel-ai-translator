<?php

namespace Livewire;

if (! class_exists(Component::class)) {
    /**
     * Minimal Livewire component stub to allow package tests to run without the full dependency.
     */
    abstract class Component
    {
        /**
         * Render the component output.
         */
        public function render()
        {
            return '';
        }
    }
}

namespace Livewire\Volt;

if (! class_exists(Component::class)) {
    /**
     * Minimal Volt component stub extending the Livewire base stub.
     */
    abstract class Component extends \Livewire\Component
    {
    }
}
