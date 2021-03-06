<?php
/**
 * ControllerNotFoundException
 *
 *
 * @package      Mooti
 * @subpackage   Framework
 * @author       Ken Lalobo <ken@mooti.io>
 */

namespace Mooti\Framework\Exception;

use Interop\Container\Exception\NotFoundException;

class ControllerNotFoundException extends \Exception implements NotFoundException
{
}