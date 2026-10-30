<?php

declare(strict_types=1);

/**
 * Derafu: File - Files, directories, paths, temporary files, archives and MIME types.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsFile;

use Derafu\File\Exception\FileNotFoundException;
use Derafu\File\File;
use Derafu\File\Translation\FileTranslationResourceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Loads the package's real configuration through a YAML consumer fixture.
 * Keep application services, translators, overrides and test locators in YAML;
 * only container bootstrapping belongs in this test's PHP infrastructure.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__) . '/fixtures/services')))
            ->load('services.yaml');

        return $container;
    }

    /** @return ServiceLocator<object> */
    private function services(ContainerBuilder $container): ServiceLocator
    {
        $container->compile();
        $services = $container->get('test.file_services');
        self::assertInstanceOf(ServiceLocator::class, $services);

        return $services;
    }

    public function testEveryConfiguredServiceCanBeConstructed(): void
    {
        $services = $this->services($this->loadContainer());
        foreach (array_keys($services->getProvidedServices()) as $id) {
            self::assertTrue(class_exists($id) || interface_exists($id));
            self::assertInstanceOf($id, $services->get($id));
        }
    }

    public function testThePackageKeepsItsServicePrivate(): void
    {
        $container = $this->loadContainer();

        self::assertFalse($container->getDefinition(FileTranslationResourceProvider::class)->isPublic());
    }

    public function testTheTaggedProviderExposesThePackageCatalogue(): void
    {
        $container = $this->loadContainer();
        self::assertArrayHasKey(
            FileTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $provider = $this->services($container)->get(FileTranslationResourceProvider::class);
        self::assertInstanceOf(FileTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        self::assertCount(1, $directories);
        self::assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
        self::assertFileExists($directories[0] . '/errors+intl-icu.es.php');
    }

    public function testTheConsumersTranslatorUsesTheTaggedResourcesForARealError(): void
    {
        $translator = $this->services($this->loadContainer())->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        try {
            File::read('/does/not/exist.txt');
            self::fail('A file that does not exist was read.');
        } catch (FileNotFoundException $exception) {
            self::assertSame('El archivo no existe: /does/not/exist.txt', $exception->trans($translator));
        }
    }
}
