<?php


namespace Nicodinus\PhpAsync\Dotenv\Store;


use Amp\Failure;
use Amp\File\Driver;
use Amp\Promise;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Store\StoreInterface;
use Nicodinus\PhpAsync\Dotenv\Store\File\Reader;
use RuntimeException;
use Throwable;
use function Amp\call;

final class FileStore implements StoreInterface
{
    /**
     * The file paths.
     *
     * @var string[]
     */
    private array $filePaths;

    /**
     * Should file loading short circuit?
     *
     * @var bool
     */
    private bool $shortCircuit;

    /**
     * The file encoding.
     *
     * @var string|null
     */
    private ?string $fileEncoding;

    /**
     * @var Reader
     */
    private Reader $reader;

    //

    /**
     * Create a new file store instance.
     *
     * @param Driver      $filesystem
     * @param string[]    $filePaths
     * @param bool        $shortCircuit
     * @param string|null $fileEncoding
     *
     * @return void
     */
    public function __construct(Driver $filesystem, array $filePaths, bool $shortCircuit, string $fileEncoding = null)
    {
        $this->reader = new Reader($filesystem);

        $this->filePaths = $filePaths;
        $this->shortCircuit = $shortCircuit;
        $this->fileEncoding = $fileEncoding;
    }

    /**
     * Read the content of the environment file(s).
     *
     * @return Promise<string>|Failure<InvalidPathException>
     */
    public function read(): Promise
    {
        return call(function () {

            if ($this->filePaths === []) {
                throw new InvalidPathException('At least one environment file path must be provided.');
            }

            try {

                $contents = yield $this->reader->read($this->filePaths, $this->shortCircuit, $this->fileEncoding);

                if (\count($contents) > 0) {
                    return \implode("\n", $contents);
                }

                throw new RuntimeException("Zero content exception");

            } catch (Throwable $exception) {

                throw new InvalidPathException(
                    \sprintf('Unable to read any of the environment file(s) at [%s].', \implode(', ', $this->filePaths)),
                    0,
                    $exception
                );

            }

        });
    }
}