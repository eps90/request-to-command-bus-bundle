<?php
declare(strict_types=1);

namespace Eps\Req2CmdBundle\Tests\DependencyInjection;

use Eps\Req2CmdBundle\Action\ApiResponderAction;
use Eps\Req2CmdBundle\CommandBus\TacticianCommandBus;
use Eps\Req2CmdBundle\CommandExtractor\JMSSerializerCommandExtractor;
use Eps\Req2CmdBundle\CommandExtractor\SerializerCommandExtractor;
use Eps\Req2CmdBundle\DependencyInjection\Req2CmdExtension;
use Eps\Req2CmdBundle\EventListener\ExtractCommandFromRequestListener;
use Eps\Req2CmdBundle\Params\ParamCollector\ParamCollector;
use Eps\Req2CmdBundle\Params\ParameterMapper\PathParamsMapper;
use Eps\Req2CmdBundle\Serializer\DeserializableCommandDenormalizer;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Reference;

class Req2CmdExtensionTest extends AbstractExtensionTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getContainerExtensions(): array
    {
        return [new Req2CmdExtension()];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->load();
    }

    /**
     * @test
     */
    public function itShouldLoadExtractorsDefinitions(): void
    {
        $this->assertContainerBuilderHasService(
            'eps.req2cmd.extractor.serializer',
            SerializerCommandExtractor::class
        );
        $this->assertContainerBuilderHasService(
            'eps.req2cmd.extractor.jms_serializer',
            JMSSerializerCommandExtractor::class
        );
        $this->assertContainerBuilderHasAlias('eps.req2cmd.extractor', 'eps.req2cmd.extractor.serializer');
    }

    /**
     * @test
     */
    public function itShouldSetAliasToDefinedExtractor(): void
    {
        $config = [
            'extractor' => [
                'service_id' => 'eps.req2cmd.extractor.jms_serializer'
            ]
        ];
        $this->load($config);

        $this->assertContainerBuilderHasAlias(
            'eps.req2cmd.extractor',
            'eps.req2cmd.extractor.jms_serializer'
        );
    }

    /**
     * @test
     */
    public function itShouldHaveActionsDefinitions(): void
    {
        $this->assertContainerBuilderHasService('eps.req2cmd.action.api_responder', ApiResponderAction::class);
    }

    /**
     * @test
     */
    public function itShouldHaveListenersDefinitions(): void
    {
        $this->assertContainerBuilderHasService(
            'eps.req2cmd.listener.extract_command',
            ExtractCommandFromRequestListener::class
        );
        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'eps.req2cmd.listener.extract_command',
            'kernel.event_listener',
            [
                'method' => 'onKernelRequest',
                'event' => 'kernel.request',
                'priority' => 0
            ]
        );
    }

    /**
     * @test
     */
    public function itShouldHaveMappersDefinitions(): void
    {
        $this->assertContainerBuilderHasService(
            'eps.req2cmd.param_mapper.path',
            PathParamsMapper::class
        );
        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'eps.req2cmd.param_mapper.path',
            'req2cmd.param_mapper',
            ['priority' => 255]
        );
    }

    /**
     * @test
     */
    public function itShouldHaveParamCollectorDefinitions(): void
    {
        $defaultCollectorId = 'eps.req2cmd.collector.param_collector';
        $this->assertContainerBuilderHasService(
            $defaultCollectorId,
            ParamCollector::class
        );
        $this->assertContainerBuilderHasAlias(
            'eps.req2cmd.param_collector',
            $defaultCollectorId
        );
    }

    /**
     * @test
     */
    public function itShouldHaveCommandBusesDefinition(): void
    {
        $defaultBusId = 'eps.req2cmd.command_bus.tactician';
        $this->assertContainerBuilderHasService($defaultBusId, TacticianCommandBus::class);
    }

    /**
     * @test
     */
    public function itShouldSetCommandBusFromConfiguration(): void
    {
        $config = [
            'command_bus' => [
                'service_id' => 'eps.req2cmd.command_bus.broadway'
            ]
        ];
        $this->load($config);

        $this->assertContainerBuilderHasAlias('eps.req2cmd.command_bus', 'eps.req2cmd.command_bus.broadway');
    }

    /**
     * @test
     */
    public function itShouldBeAbleToChooseOtherCommandBusThanDefault(): void
    {
        $config = [
            'command_bus' => [
                'service_id' => 'eps.req2cmd.command_bus.tactician',
                'name' => 'queued'
            ]
        ];

        $this->load($config);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'eps.req2cmd.command_bus.tactician',
            0,
            new Reference('tactician.commandbus.queued')
        );
    }

    /**
     * @test
     */
    public function itShouldBeAbleToChangeListenersPriority(): void
    {
        $config = [
            'listeners' => [
                'extractor' => [
                    'priority' => 128
                ]
            ]
        ];

        $this->load($config);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'eps.req2cmd.listener.extract_command',
            'kernel.event_listener',
            [
                'method' => 'onKernelRequest',
                'event' => 'kernel.request',
                'priority' => 128
            ]
        );
    }

    /**
     * @test
     */
    public function itShouldBeAbleToDisableAListener(): void
    {
        $config = [
            'listeners' => [
                'extractor' => [
                    'enabled' => false
                ]
            ]
        ];

        $this->load($config);

        $this->assertContainerBuilderNotHasService('eps.req2cmd.listener.extract_command');
    }

    /**
     * @test
     */
    public function itShouldHaveADenormalizerDefinition(): void
    {
        $denormalizerId = 'eps.req2cmd.normalizer.deserializable_command_denormalizer';
        $this->assertContainerBuilderHasService($denormalizerId, DeserializableCommandDenormalizer::class);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            $denormalizerId,
            'serializer.normalizer'
        );
    }

    /**
     * @test
     */
    public function itShouldNotHaveDenormalizerDefinitionIfItIsDisabled(): void
    {
        $config = [
            'extractor' => [
                'use_cmd_denormalizer' => false
            ]
        ];
        $this->load($config);

        $this->assertContainerBuilderNotHasService('eps.req2cmd.normalizer.deserializable_command_denormalizer');
    }
}
