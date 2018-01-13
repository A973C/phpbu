<?php
namespace phpbu\App\Configuration\Loader;

use phpbu\App\Configuration\Bootstrapper;

/**
 * Loader Factory test
 *
 * @package    phpbu
 * @subpackage tests
 * @author     Sebastian Feldmann <sebastian@phpbu.de>
 * @copyright  Sebastian Feldmann <sebastian@phpbu.de>
 * @license    https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link       https://www.phpbu.de/
 * @since      Class available since Release 3.0.0
 */
class FactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Tests Factory::createLoader
     */
    public function testCreateLoader()
    {
        $file   = PHPBU_TEST_FILES . '/conf/json/config-valid.json';
        $loader = Factory::createLoader($file, new Bootstrapper());

        $this->assertTrue($loader instanceof Json);
    }

    /**
     * Tests Factory::createLoader
     */
    public function testCreateLoaderNoBootstrapper()
    {
        $file   = PHPBU_TEST_FILES . '/conf/json/config-valid.json';
        $loader = Factory::createLoader($file);

        $this->assertTrue($loader instanceof Json);
    }
}
