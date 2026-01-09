<?php
/**
 * NotFoundException class
 *
 * @package SmartAutoUploadImages
 */

namespace SmartAutoUploadImages\Exception;

use SmartAutoUploadImages\Vendor_Prefixed\Psr\Container\NotFoundExceptionInterface;

/**
 * NotFoundException class
 */
class NotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface {}
