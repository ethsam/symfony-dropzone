<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Tests\Fixtures;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\ManagerRegistry;

/**
 * An in-memory SQLite stack, the smallest thing that lets the single-file mode
 * of the widget run for real. EntityType resolves its choices through a
 * ManagerRegistry, so a mock cannot stand in for one.
 */
final class DoctrineKit
{
    /**
     * @param list<array{id: int, filename: string, src: string, size?: int|null, mimetype?: string|null}> $rows
     */
    public static function registry(array $rows = []): ManagerRegistry
    {
        $entityManager = self::entityManager();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema([$entityManager->getClassMetadata(StoredFile::class)]);

        foreach ($rows as $row) {
            $file = (new StoredFile())
                ->setId($row['id'])
                ->setFilename($row['filename'])
                ->setSrc($row['src'])
                ->setSize($row['size'] ?? null)
                ->setMimetype($row['mimetype'] ?? null);

            $entityManager->persist($file);
        }

        $entityManager->flush();
        $entityManager->clear();

        return new class(['default' => $entityManager]) extends AbstractManagerRegistry {
            /**
             * @param array<string, EntityManagerInterface> $managers
             */
            public function __construct(private readonly array $managers)
            {
                parent::__construct(
                    'dropzone-tests',
                    [],
                    array_combine(array_keys($managers), array_keys($managers)),
                    '',
                    'default',
                    \Doctrine\Persistence\Proxy::class,
                );
            }

            protected function getService($name): object
            {
                return $this->managers[$name];
            }

            protected function resetService($name): void
            {
            }
        };
    }

    private static function entityManager(): EntityManagerInterface
    {
        // Configuration is assembled by hand rather than through ORMSetup, which
        // would drag symfony/cache in as a dependency just to run the tests.
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__]));
        $proxyDir = sys_get_temp_dir().'/dropzone-test-proxies';

        if (!is_dir($proxyDir) && !mkdir($proxyDir, 0777, true) && !is_dir($proxyDir)) {
            throw new \RuntimeException(sprintf('Could not create the proxy directory "%s".', $proxyDir));
        }

        $config->setProxyDir($proxyDir);
        $config->setProxyNamespace('DropzoneTestProxies');
        $config->setAutoGenerateProxyClasses(true);

        // ORM 3 builds proxies from symfony/var-exporter unless PHP's own lazy
        // objects are switched on. Older setups fall back to var-exporter.
        if (\PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        // No ORM Configuration here: ORM 3 stopped extending the DBAL one, so
        // passing it through would break on half the supported range.
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        // ORM 2 keeps the constructor protected and exposes a factory; ORM 3
        // removed the factory. Both live in this bundle's supported range.
        if (method_exists(EntityManager::class, 'create')) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = EntityManager::create($connection, $config);

            return $entityManager;
        }

        return new EntityManager($connection, $config);
    }
}
