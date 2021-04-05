<?php


namespace Nicodinus\PhpAsync\Dotenv;


use Amp\Failure;
use Amp\File\Driver;
use Amp\Promise;
use Closure;
use Dotenv\Exception\InvalidEncodingException;
use Dotenv\Exception\InvalidFileException;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Loader\Loader;
use Dotenv\Loader\LoaderInterface;
use Dotenv\Parser\Parser;
use Dotenv\Parser\ParserInterface;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Dotenv\Repository\RepositoryInterface;
use Dotenv\Store\StoreInterface;
use Dotenv\Store\StringStore;
use Dotenv\Validator;
use Nicodinus\PhpAsync\Dotenv\Store\StoreBuilder;
use function Amp\call;
use function Amp\File\filesystem;

class AsyncDotenv
{
    /** @var Driver */
    private static Driver $filesystem;

    /**
     * The store instance.
     *
     * @var StoreInterface
     */
    private StoreInterface $store;

    /**
     * The parser instance.
     *
     * @var ParserInterface
     */
    private ParserInterface $parser;

    /**
     * The loader instance.
     *
     * @var LoaderInterface
     */
    private LoaderInterface $loader;

    /**
     * The repository instance.
     *
     * @var RepositoryInterface
     */
    private RepositoryInterface $repository;

    /**
     * Create a new dotenv instance.
     *
     * @param StoreInterface $store
     * @param ParserInterface $parser
     * @param LoaderInterface $loader
     * @param RepositoryInterface $repository
     *
     * @return void
     */
    public function __construct(
        StoreInterface $store,
        ParserInterface $parser,
        LoaderInterface $loader,
        RepositoryInterface $repository
    ) {
        $this->store = $store;
        $this->parser = $parser;
        $this->loader = $loader;
        $this->repository = $repository;
    }

    /**
     * @param Driver $filesystem
     *
     * @return void
     */
    public static function setFilesystem(Driver $filesystem): void
    {
        static::$filesystem = $filesystem;
    }

    /**
     * Create a new dotenv instance.
     *
     * @param RepositoryInterface $repository
     * @param string|string[]                        $paths
     * @param string|string[]|null                   $names
     * @param bool                                   $shortCircuit
     * @param string|null                            $fileEncoding
     *
     * @return static
     */
    public static function create(RepositoryInterface $repository, $paths, $names = null, bool $shortCircuit = true, string $fileEncoding = null)
    {
        $builder = $names === null ? StoreBuilder::createWithDefaultName() : StoreBuilder::createWithNoNames();

        foreach ((array) $paths as $path) {
            $builder = $builder->addPath($path);
        }

        foreach ((array) $names as $name) {
            $builder = $builder->addName($name);
        }

        if ($shortCircuit) {
            $builder = $builder->shortCircuit();
        }

        return new self($builder->fileEncoding($fileEncoding)->make(empty(static::$filesystem) ? filesystem() : static::$filesystem), new Parser(), new Loader(), $repository);
    }

    /**
     * Create a new mutable dotenv instance with default repository.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     * @param bool                 $shortCircuit
     * @param string|null          $fileEncoding
     *
     * @return static
     */
    public static function createMutable($paths, $names = null, bool $shortCircuit = true, string $fileEncoding = null)
    {
        $repository = RepositoryBuilder::createWithDefaultAdapters()->make();

        return self::create($repository, $paths, $names, $shortCircuit, $fileEncoding);
    }

    /**
     * Create a new mutable dotenv instance with default repository with the putenv adapter.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     * @param bool                 $shortCircuit
     * @param string|null          $fileEncoding
     *
     * @return static
     */
    public static function createUnsafeMutable($paths, $names = null, bool $shortCircuit = true, string $fileEncoding = null)
    {
        $repository = RepositoryBuilder::createWithDefaultAdapters()
            ->addAdapter(PutenvAdapter::class)
            ->make();

        return self::create($repository, $paths, $names, $shortCircuit, $fileEncoding);
    }

    /**
     * Create a new immutable dotenv instance with default repository.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     * @param bool                 $shortCircuit
     * @param string|null          $fileEncoding
     *
     * @return static
     */
    public static function createImmutable($paths, $names = null, bool $shortCircuit = true, string $fileEncoding = null)
    {
        $repository = RepositoryBuilder::createWithDefaultAdapters()->immutable()->make();

        return self::create($repository, $paths, $names, $shortCircuit, $fileEncoding);
    }

    /**
     * Create a new immutable dotenv instance with default repository with the putenv adapter.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     * @param bool                 $shortCircuit
     * @param string|null          $fileEncoding
     *
     * @return static
     */
    public static function createUnsafeImmutable($paths, $names = null, bool $shortCircuit = true, string $fileEncoding = null)
    {
        $repository = RepositoryBuilder::createWithDefaultAdapters()
            ->addAdapter(PutenvAdapter::class)
            ->immutable()
            ->make();

        return self::create($repository, $paths, $names, $shortCircuit, $fileEncoding);
    }

    /**
     * Create a new dotenv instance with an array backed repository.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     * @param bool                 $shortCircuit
     * @param string|null          $fileEncoding
     *
     * @return static
     */
    public static function createArrayBacked($paths, $names = null, bool $shortCircuit = true, string $fileEncoding = null)
    {
        $repository = RepositoryBuilder::createWithNoAdapters()->addAdapter(ArrayAdapter::class)->make();

        return self::create($repository, $paths, $names, $shortCircuit, $fileEncoding);
    }

    /**
     * Parse the given content and resolve nested variables.
     *
     * This method behaves just like load(), only without mutating your actual
     * environment. We do this by using an array backed repository.
     *
     * @param string $content
     *
     * @return Promise<array<string,string|null>>|Failure<InvalidPathException|InvalidEncodingException|InvalidFileException>
     */
    public static function parse(string $content): Promise
    {
        $repository = RepositoryBuilder::createWithNoAdapters()->addAdapter(ArrayAdapter::class)->make();

        $phpdotenv = new self(new StringStore($content), new Parser(), new Loader(), $repository);

        return $phpdotenv->load();
    }

    /**
     * Read and load environment file(s).
     *
     * @return Promise<array<string,string|null>>|Failure<InvalidPathException|InvalidEncodingException|InvalidFileException>
     */
    public function load(): Promise
    {
        return call(function () {

            $read = yield call(Closure::fromCallable([$this->store, 'read']));

            $entries = $this->parser->parse($read);

            return $this->loader->load($this->repository, $entries);

        });
    }

    /**
     * Read and load environment file(s), silently failing if no files can be read.
     *
     * @return Promise<array<string,string|null>>|Failure<InvalidEncodingException|InvalidFileException>
     */
    public function safeLoad(): Promise
    {
        return call(function () {

            try {
                return yield $this->load();
            } catch (InvalidPathException $e) {
                // suppressing exception
                return [];
            }

        });
    }

    /**
     * Required ensures that the specified variables exist, and returns a new validator object.
     *
     * @param string|string[] $variables
     *
     * @return Validator
     */
    public function required($variables)
    {
        return (new Validator($this->repository, (array) $variables))->required();
    }

    /**
     * Returns a new validator object that won't check if the specified variables exist.
     *
     * @param string|string[] $variables
     *
     * @return Validator
     */
    public function ifPresent($variables)
    {
        return new Validator($this->repository, (array) $variables);
    }
}