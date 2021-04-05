<?php


namespace Nicodinus\PhpAsync\Dotenv\Store\File;


use Amp\Failure;
use Amp\File\Driver;
use Amp\File\FilesystemException;
use Amp\Promise;
use Dotenv\Exception\InvalidEncodingException;
use Dotenv\Util\Str;
use PhpOption\Option;
use function Amp\call;

/**
 * Class Reader
 *
 * @package Nicodinus\PhpAsync\Dotenv\Store\File
 *
 * @internal
 */
final class Reader
{
    /** @var Driver */
    private Driver $filesystem;

    //

    /**
     * Reader constructor.
     *
     * @param Driver $filesystem
     */
    public function __construct(Driver $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Read the file(s), and return their raw content.
     *
     * We provide the file path as the key, and its content as the value. If
     * short circuit mode is enabled, then the returned array with have length
     * at most one. File paths that couldn't be read are omitted entirely.
     *
     * @param string[]    $filePaths
     * @param bool        $shortCircuit
     * @param string|null $fileEncoding
     *
     * @return Promise<array<string,string>>|Failure<InvalidEncodingException|FilesystemException>
     */
    public function read(array $filePaths, bool $shortCircuit = true, string $fileEncoding = null): Promise
    {
        return call(function () use (&$filePaths, &$shortCircuit, &$fileEncoding) {

            $output = [];

            foreach ($filePaths as $filePath) {

                /** @var Option<string> $content */
                $content = yield $this->readFromFile($filePath, $fileEncoding);

                if ($content->isDefined()) {

                    $output[$filePath] = $content->get();

                    if ($shortCircuit) {
                        break;
                    }

                }

            }

            return $output;

        });
    }

    /**
     * Read the given file.
     *
     * @param string      $path
     * @param string|null $encoding
     *
     * @return Promise<Option<string>>|Failure<InvalidEncodingException|FilesystemException>
     */
    private function readFromFile(string $path, string $encoding = null): Promise
    {
        return call(function () use (&$path, &$encoding) {

            /** @var Option<string> */
            $content = Option::fromValue(yield $this->filesystem->get($path), false);

            return $content->flatMap(static function (string $content) use ($encoding) {

                return Str::utf8($content, $encoding)->mapError(static function (string $error) {

                    throw new InvalidEncodingException($error);

                })->success();

            });

        });
    }
}