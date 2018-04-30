<?php
namespace phpbu\App\Backup;

use phpbu\App\Backup\File\FileLocal;
use phpbu\App\Exception;
use phpbu\App\Util\Str;

/**
 * Backup Target class.
 *
 * @package    phpbu
 * @subpackage Backup
 * @author     Sebastian Feldmann <sebastian@phpbu.de>
 * @copyright  Sebastian Feldmann <sebastian@phpbu.de>
 * @license    https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link       https://phpbu.de/
 * @since      Class available since Release 1.0.0
 */
class Target
{
    /**
     * Absolute path to the directory where to store the backup.
     *
     * @var string
     */
    private $path;

    /**
     * Path to the backup with potential date placeholders like %d.
     *
     * @var string
     */
    private $pathRaw;

    /**
     * Indicates if the path changes over time.
     *
     * @var bool
     */
    private $pathIsChanging = false;

    /**
     * Part of the path without placeholders
     *
     * @var string
     */
    private $pathNotChanging;

    /**
     * List of directories containing date placeholders
     *
     * @var string[]
     */
    private $pathElementsChanging = [];

    /**
     * Backup filename.
     *
     * @var string
     */
    private $filename;

    /**
     * Filename with potential date placeholders like %d.
     *
     * @var string
     */
    private $filenameRaw;

    /**
     * List of custom file suffixes f.e. 'tar'
     *
     * @var string[]
     */
    private $fileSuffixes = [];

    /**
     * Indicates if the filename changes over time.
     *
     * @var bool
     */
    private $filenameIsChanging = false;

    /**
     * Target MIME type
     *
     * @var string
     */
    private $mimeType = 'text/plain';

    /**
     * Size in bytes
     *
     * @var int
     */
    private $size;

    /**
     * Should the file be compressed.
     *
     * @var bool
     */
    private $compress = false;

    /**
     * File compression.
     *
     * @var \phpbu\App\Backup\Target\Compression
     */
    private $compression;

    /**
     * Should the file be encrypted.
     *
     * @var bool
     */
    private $crypt = false;

    /**
     * File crypter.
     *
     * @var \phpbu\App\Backup\Crypter
     */
    private $crypter;

    /**
     * Constructor.
     *
     * @param  string  $path
     * @param  string  $filename
     * @param  integer $time
     * @throws \phpbu\App\Exception
     */
    public function __construct($path, $filename, $time = null)
    {
        $this->setPath($path, $time);
        $this->setFile($filename, $time);
    }

    /**
     * Directory setter.
     *
     * @param  string $path
     * @param  int    $time
     * @throws \phpbu\App\Exception
     */
    public function setPath($path, $time = null)
    {
        $this->pathRaw = $path;
        if (Str::isContainingPlaceholder($path)) {
            $this->pathIsChanging = true;
            $this->detectChangingPathElements($path);
            // replace potential date placeholder
            $path = Str::replaceDatePlaceholders($path, $time);
        } else {
            $this->pathNotChanging = $path;
        }
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Find path elements that can change because of placeholder usage.
     *
     * @param string $path
     */
    private function detectChangingPathElements(string $path)
    {
        // path should be absolute so we remove the root slash to prevent empty first element
        $dirs = explode('/', substr($path, 1));

        // check all path elements for placeholders
        // this is important to effectively generate search regex to collect backups
        $this->pathNotChanging = '';
        $foundChangingElement  = false;
        foreach ($dirs as $d) {
            // already found placeholder or found one right now
            // if not add directory to not changing part of the path
            if ($foundChangingElement || Str::isContainingPlaceholder($d)) {
                $this->pathElementsChanging[] = $d;
                $foundChangingElement         = true;
            } else {
                $this->pathNotChanging .= DIRECTORY_SEPARATOR . $d;
            }
        }
    }

    /**
     * Filename setter.
     *
     * @param string $file
     * @param int    $time
     */
    public function setFile($file, $time = null)
    {
        $this->filenameRaw = $file;
        if (Str::isContainingPlaceholder($file)) {
            $this->filenameIsChanging = true;
            $file                     = Str::replaceDatePlaceholders($file, $time);
        }
        $this->filename = $file;
    }

    /**
     * Append another suffix to the filename.
     *
     * @param string $suffix
     */
    public function appendFileSuffix(string $suffix)
    {
        $this->fileSuffixes[] = $suffix;
    }

    /**
     * Checks if the backup target directory is writable.
     * Creates the Directory if it doesn't exist.
     *
     * @throws \phpbu\App\Exception
     */
    public function setupPath()
    {
        // if directory doesn't exist, create it
        if (!is_dir($this->path)) {
            $reporting = error_reporting();
            error_reporting(0);
            $created = mkdir($this->path, 0755, true);
            error_reporting($reporting);
            if (!$created) {
                throw new Exception(sprintf('cant\'t create directory: %s', $this->path));
            }
        }
        if (!is_writable($this->path)) {
            throw new Exception(sprintf('no write permission for directory: %s', $this->path));
        }
    }

    /**
     * Target file MIME type setter.
     *
     * @param string $mime
     */
    public function setMimeType(string $mime)
    {
        $this->mimeType = $mime;
    }

    /**
     * Return the path to the backup file.
     *
     * @return string
     */
    public function getPath() : string
    {
        return $this->path;
    }

    /**
     * Return the path to the backup file.
     *
     * @return string
     */
    public function getPathRaw() : string
    {
        return $this->pathRaw;
    }

    /**
     * Return the name to the backup file.
     *
     * @param  bool $plain
     * @return string
     */
    public function getFilename(bool $plain = false) : string
    {
        return $this->filename . $this->getFilenameSuffix($plain);
    }

    /**
     * Return the name of the backup file without compressor or encryption suffix.
     *
     * @return string
     */
    public function getFilenamePlain() : string
    {
        return $this->getFilename(true);
    }

    /**
     * Return the raw name of the backup file incl. date placeholder.
     *
     * @param  bool $plain
     * @return string
     */
    public function getFilenameRaw($plain = false) : string
    {
        return $this->filenameRaw . $this->getFilenameSuffix($plain);
    }

    /**
     * Return custom file suffix like '.tar'.
     *
     * @param  bool $plain
     * @return string
     */
    public function getFilenameSuffix($plain = false) : string
    {
        return $this->getSuffixToAppend() . ($plain ? '' : $this->getCompressionSuffix() . $this->getCrypterSuffix());
    }

    /**
     * Return added suffixes.
     *
     * @return string
     */
    public function getSuffixToAppend() : string
    {
        return count($this->fileSuffixes) ? '.' . implode('.', $this->fileSuffixes) : '';
    }

    /**
     * Return the compressor suffix.
     *
     * @return string
     */
    public function getCompressionSuffix() : string
    {
        return $this->shouldBeCompressed() ? '.' . $this->compression->getSuffix() : '';
    }

    /**
     * Return the crypter suffix.
     *
     * @return string
     */
    public function getCrypterSuffix() : string
    {
        return $this->shouldBeEncrypted() ? '.' . $this->crypter->getSuffix() : '';
    }

    /**
     * Return file MIME type.
     *
     * @return string
     */
    public function getMimeType() : string
    {
        $mimeType = $this->mimeType;
        if ($this->shouldBeCompressed()) {
            $mimeType = $this->compression->getMimeType();
        }
        return $mimeType;
    }

    /**
     * Size setter.
     *
     * @param int $size
     */
    public function setSize(int $size)
    {
        $this->size = $size;
    }

    /**
     * Return the actual file size in bytes.
     *
     * @return int
     * @throws \phpbu\App\Exception
     */
    public function getSize() : int
    {
        if (null === $this->size) {
            if (!file_exists($this)) {
                throw new Exception(sprintf('target file \'%s\' doesn\'t exist', $this->getFilename()));
            }
            $this->size = filesize($this);
        }
        return $this->size;
    }

    /**
     * Target file exists already.
     *
     * @param  bool $plain
     * @return bool
     */
    public function fileExists(bool $plain = false) : bool
    {
        return file_exists($this->getPathname($plain));
    }

    /**
     * Return as backup file object.
     *
     * @return \phpbu\App\Backup\File\FileLocal
     */
    public function toFile() : FileLocal
    {
        return new FileLocal(new \SplFileInfo($this->getPathname()));
    }

    /**
     * Return path and filename of the backup file.
     *
     * @param  bool $plain
     * @return string
     */
    public function getPathname(bool $plain = false) : string
    {
        return $this->path . DIRECTORY_SEPARATOR . $this->getFilename($plain);
    }

    /**
     * Return path and plain filename of the backup file.
     *
     * @return string
     */
    public function getPathnamePlain() : string
    {
        return $this->getPathname(true);
    }

    /**
     * Is dirname configured with any date placeholders.
     *
     * @return bool
     */
    public function hasChangingPath() : bool
    {
        return $this->pathIsChanging;
    }

    /**
     * Return the part of the path that is not changing.
     *
     * @return string
     */
    public function getPathThatIsNotChanging() : string
    {
        return $this->pathNotChanging;
    }

    /**
     * Changing path elements getter.
     *
     * @return array
     */
    public function getChangingPathElements() : array
    {
        return $this->pathElementsChanging;
    }

    /**
     * Return amount of changing path elements.
     *
     * @return int
     */
    public function countChangingPathElements() : int
    {
        return count($this->pathElementsChanging);
    }

    /**
     * Filename configured with any date placeholders.
     *
     * @return bool
     */
    public function hasChangingFilename() : bool
    {
        return $this->filenameIsChanging;
    }

    /**
     * Disable file compression.
     */
    public function disableCompression()
    {
        $this->compress = false;
    }

    /**
     * Enable file compression.
     *
     * @throws \phpbu\App\Exception
     */
    public function enableCompression()
    {
        if (null == $this->compression) {
            throw new Exception('can\'t enable compression without a compressor');
        }
        $this->compress = true;
    }

    /**
     * Compression setter.
     *
     * @param \phpbu\App\Backup\Target\Compression $compression
     */
    public function setCompression(Target\Compression $compression)
    {
        $this->compression = $compression;
        $this->compress    = true;
    }

    /**
     * Compressor getter.
     *
     * @return \phpbu\App\Backup\Target\Compression
     */
    public function getCompression() : Target\Compression
    {
        return $this->compression;
    }

    /**
     * Is a compressor set?
     *
     * @return bool
     */
    public function shouldBeCompressed() : bool
    {
        return $this->compress !== false;
    }

    /**
     * Crypter setter.
     *
     * @param \phpbu\App\Backup\Crypter $crypter
     */
    public function setCrypter(Crypter $crypter)
    {
        $this->crypter = $crypter;
        $this->crypt   = true;
    }

    /**
     * Crypter getter.
     *
     * @return \phpbu\App\Backup\Crypter
     */
    public function getCrypter() : Crypter
    {
        return $this->crypter;
    }

    /**
     * Disable file encryption.
     */
    public function disableEncryption()
    {
        $this->crypt = false;
    }

    /**
     * Is a crypter set?
     *
     * @return bool
     */
    public function shouldBeEncrypted() : bool
    {
        return $this->crypt !== false;
    }

    /**
     * Magic to string method.
     *
     * @return string
     */
    public function __toString() : string
    {
        return $this->getPathname();
    }
}
