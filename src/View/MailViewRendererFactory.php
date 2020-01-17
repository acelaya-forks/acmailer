<?php

declare(strict_types=1);

namespace AcMailer\View;

use Interop\Container\ContainerInterface;
use Interop\Container\ContainerInterface as InteropContainer;
use Interop\Container\Exception\ContainerException;
use Interop\Container\Exception\NotFoundException;
use Laminas\Mvc\Service\ViewHelperManagerFactory;
use Laminas\ServiceManager\Config;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Renderer\RendererInterface;
use Laminas\View\Resolver\AggregateResolver;
use Laminas\View\Resolver\ResolverInterface;
use Laminas\View\Resolver\TemplateMapResolver;
use Laminas\View\Resolver\TemplatePathStack;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerExceptionInterface;

use function array_shift;
use function count;

class MailViewRendererFactory
{
    /** @deprecated Use the MailViewRendererInterface FQCN instead */
    public const SERVICE_NAME = MailViewRendererInterface::class;

    /**
     * @throws ContainerExceptionInterface
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function __invoke(ContainerInterface $container): MailViewRendererInterface
    {
        // First, if the TemplateRendererInterface is registered as a service, use that service.
        // This should be true in expressive applications
        if ($container->has(TemplateRendererInterface::class)) {
            return new MezzioMailViewRenderer($container->get(TemplateRendererInterface::class));
        }

        // If the mailviewrenderer is registered, wrap it into a LaminasViewRenderer
        // This should be true in Laminas/MVC apps, run in a HTTP context
        if ($container->has('mailviewrenderer')) {
            return $this->wrapLaminasView($container->get('mailviewrenderer'));
        }

        // Finally, create a laminas/view PhpRenderer and wrap it into a LaminasViewRenderer
        // This should be reached only in Laminas/MVC apps run in a CLI context
        $vmConfig = $this->getSpecificConfig($container, 'view_manager');
        $renderer = new PhpRenderer();

        // Check what kind of view_manager configuration has been defined
        $resolversStack = [];
        if (isset($vmConfig['template_map'])) {
            // Create a TemplateMapResolver in case only the template_map has been defined
            $resolversStack[] = new TemplateMapResolver($vmConfig['template_map']);
        }
        if (isset($vmConfig['template_path_stack'])) {
            // Create a TemplatePathStack resolver in case only the template_path_stack has been defined
            $pathStackResolver = new TemplatePathStack();
            $pathStackResolver->setPaths($vmConfig['template_path_stack']);
            $resolversStack[] = $pathStackResolver;
        }

        // Create the template resolver for the PhpRenderer
        $resolver = $this->buildTemplateResolverFromStack($resolversStack);
        if ($resolver !== null) {
            $renderer->setResolver($resolver);
        }

        // Create a HelperPluginManager with default view helpers and user defined view helpers
        $renderer->setHelperPluginManager($this->createHelperPluginManager($container));
        return $this->wrapLaminasView($renderer);
    }

    private function wrapLaminasView(RendererInterface $renderer): MailViewRendererInterface
    {
        return new MvcMailViewRenderer($renderer);
    }

    /**
     * Creates a view helper manager
     * @param ContainerInterface|InteropContainer $container
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function createHelperPluginManager(ContainerInterface $container): HelperPluginManager
    {
        $factory = new ViewHelperManagerFactory();
        /** @var HelperPluginManager $helperManager */
        $helperManager = $factory($container, ViewHelperManagerFactory::PLUGIN_MANAGER_CLASS);
        $config = new Config($this->getSpecificConfig($container, 'view_helpers'));
        $config->configureServiceManager($helperManager);
        return $helperManager;
    }

    /**
     * Returns a specific configuration defined by provided key
     * @return array
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function getSpecificConfig(ContainerInterface $container, string $configKey): array
    {
        return $container->get('config')[$configKey] ?? [];
    }

    /**
     * @param array $resolversStack
     */
    private function buildTemplateResolverFromStack(array $resolversStack): ?ResolverInterface
    {
        if (count($resolversStack) <= 1) {
            return array_shift($resolversStack);
        }

        // Attach all resolvers to the aggregate, if there's more than one
        $aggregateResolver = new AggregateResolver();
        foreach ($resolversStack as $resolver) {
            $aggregateResolver->attach($resolver);
        }
        return $aggregateResolver;
    }
}
