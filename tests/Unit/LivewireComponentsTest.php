<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests\Unit;

use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\QueueStatus;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Sync;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\WatchLogs;
use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;

class LivewireComponentsTest extends TestCase
{
    /** @test */
    public function queue_status_component_can_be_instantiated()
    {
        $component = new QueueStatus;

        $this->assertInstanceOf(QueueStatus::class, $component);
    }

    /** @test */
    public function sync_component_can_be_instantiated()
    {
        $component = new Sync;

        $this->assertInstanceOf(Sync::class, $component);
    }

    /** @test */
    public function watch_logs_component_can_be_instantiated()
    {
        $component = new WatchLogs;

        $this->assertInstanceOf(WatchLogs::class, $component);
    }

    /** @test */
    public function queue_status_component_has_correct_properties()
    {
        $component = new QueueStatus;

        $this->assertTrue(property_exists($component, 'completed'));
        $this->assertTrue(property_exists($component, 'total'));
        $this->assertTrue(property_exists($component, 'failed'));
    }

    /** @test */
    public function sync_component_has_correct_properties()
    {
        $component = new Sync;

        $this->assertTrue(property_exists($component, 'files'));
        $this->assertTrue(property_exists($component, 'availableLocales'));
        $this->assertTrue(property_exists($component, 'availableProviders'));
        $this->assertTrue(property_exists($component, 'isProcessing'));
        $this->assertTrue(property_exists($component, 'progress'));
    }

    /** @test */
    public function watch_logs_component_has_correct_properties()
    {
        $component = new WatchLogs;

        $this->assertTrue(property_exists($component, 'logs'));
        $this->assertTrue(property_exists($component, 'page'));
        $this->assertTrue(property_exists($component, 'perPage'));
        $this->assertTrue(property_exists($component, 'filter'));
        $this->assertTrue(property_exists($component, 'autoRefresh'));
    }

    /** @test */
    public function watch_logs_component_can_determine_log_levels()
    {
        $component = new WatchLogs;

        // Test the method using reflection since it's protected
        $reflection = new \ReflectionClass($component);
        $method = $reflection->getMethod('determineLogLevel');
        $method->setAccessible(true);

        $this->assertEquals('error', $method->invoke($component, 'Error message'));
        $this->assertEquals('warning', $method->invoke($component, 'Warning message'));
        $this->assertEquals('info', $method->invoke($component, 'Info message'));
    }

    /** @test */
    public function watch_logs_component_can_format_context()
    {
        $component = new WatchLogs;

        $context = ['key1' => 'value1', 'key2' => 'value2'];
        $formatted = $component->formatContext($context);

        $this->assertStringContainsString('key1: value1', $formatted);
        $this->assertStringContainsString('key2: value2', $formatted);
    }

    /** @test */
    public function watch_logs_component_returns_correct_log_level_colors()
    {
        $component = new WatchLogs;

        $this->assertStringContainsString('text-red-600 bg-red-100', $component->getLogLevelColor('error'));
        $this->assertStringContainsString('text-yellow-600 bg-yellow-100', $component->getLogLevelColor('warning'));
        $this->assertStringContainsString('text-blue-600 bg-blue-100', $component->getLogLevelColor('info'));
    }
}
