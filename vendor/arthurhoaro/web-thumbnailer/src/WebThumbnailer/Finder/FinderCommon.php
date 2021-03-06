<?php

namespace WebThumbnailer\Finder;

use WebThumbnailer\Application\WebAccess;
use WebThumbnailer\Exception\BadRulesException;
use WebThumbnailer\Utils\SizeUtils;
use WebThumbnailer\WebThumbnailer;

/**
 * Abstract Class FinderCommon
 *
 * Define multiple attributes and methods which can be shared between finders.
 *
 * @package WebThumbnailer\Finder
 */
abstract class FinderCommon implements Finder
{
    /**
     * Key which represent the size.
     */
    const SIZE_KEY = 'size';

    /**
     * @var string Current domain used by this finder.
     */
    protected $domain;

    /**
     * @var string URL provided by the user.
     */
    protected $url;

    /**
     * @var array List of options from this Finder rules.
     */
    protected $finderOptions;

    /**
     * @var array List of options provided by the user.
     */
    protected $userOptions;

    /**
     * Replace a part of the thumbnail URL from an option provided by the user, and known by this Finder.
     * URL options must be defined between `${}`.
     * Example:
     *      if ${size} is provided with the regex Finder rule `thumbnail_url`,
     *      it will be replaced by the proper size from $finderOptions, depending of size options in $userOptions.
     *
     * @param string $thumbnailUrl Thumbnail resolved URL.
     * @param string $option Option to replace.
     *
     * @return string Thumbnail URL updated with the proper option placeholder replacement.
     *
     * @throws BadRulesException
     */
    protected function replaceOption($thumbnailUrl, $option)
    {
        $chosenOption = $this->getOptionValue($option);
        return str_replace('${'. $option .'}', $chosenOption, $thumbnailUrl);
    }

    /**
     *
     *
     * @param $option
     * @return mixed
     *
     * @throws BadRulesException
     */
    protected function getOptionValue($option)
    {
        // If the provided option is not defined in the Finder rules.
        if (empty($this->finderOptions) || ! in_array($option, array_keys($this->finderOptions))) {
            throw new BadRulesException('Unknown option "'. $option .'" for the finder "'. $this->getName() .'"');
        }

        // User option is defined.
        // Any defined option must provide a replacement value in rules under the `param` key.
        if (! empty($this->userOptions[$option])
            && is_string($this->userOptions[$option])
            && isset($this->finderOptions[$option][$this->userOptions[$option]]['param'])
        ) {
            return $this->finderOptions[$option][$this->userOptions[$option]]['param'];
        }

        // If no user option has been found, and no default value is provided: error.
        if (! isset($this->finderOptions[$option]['default'])) {
            $error = 'No default set for option "'. $option .'" for the finder "'. $this->getName() .'"';
            throw new BadRulesException($error);
        }

        // Use default option replacement.
        $default = $this->finderOptions[$option]['default'];
        if (!isset($this->finderOptions[$option][$default]['param'])) {
            $error = 'No default parameter set for option "'. $option .'" for the finder "'. $this->getName() .'"';
            throw new BadRulesException($error);
        }

        return $this->finderOptions[$option][$default]['param'];
    }

    /**
     * @inheritdoc
     */
    public function isHotlinkAllowed()
    {
        if (! isset($this->finderOptions['hotlink_allowed'])
            ||  $this->finderOptions['hotlink_allowed'] === true
        ) {
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function setUserOptions($userOptions)
    {
        $this->userOptions = $userOptions;

        $this->setSizeOption();
    }

    /**
     * Set size parameter properly.
     *
     * If something goes wrong, we just ignore it.
     * The size user setting can be set to small, medium, etc. or a pixel value (int).
     *
     * We retrieve the thumbnail size bigger than the minimal size asked.
     */
    protected function setSizeOption()
    {
        // If no option has been provided, we'll use default values.
        if (empty($this->userOptions[WebThumbnailer::MAX_HEIGHT])
            && empty($this->userOptions[WebThumbnailer::MAX_WIDTH])
        ) {
            return;
        }

        // If the rules doesn't specify anything about size, abort.
        if (empty($this->finderOptions[self::SIZE_KEY])) {
            return;
        }

        // Load height user option.
        if (!empty($this->userOptions[WebThumbnailer::MAX_HEIGHT])) {
            $height = $this->userOptions[WebThumbnailer::MAX_HEIGHT];
            if (SizeUtils::isMetaSize($height)) {
                $height = SizeUtils::getMetaSize($height);
            }
        }

        // Load width user option.
        if (!empty($this->userOptions[WebThumbnailer::MAX_WIDTH])) {
            $width = $this->userOptions[WebThumbnailer::MAX_WIDTH];
            if (SizeUtils::isMetaSize($width)) {
                $width = SizeUtils::getMetaSize($width);
            }
        }

        // Trying to find a resolution higher than the one asked.
        foreach ($this->finderOptions[self::SIZE_KEY] as $sizeOption => $value) {
            if ($sizeOption == 'default') {
                continue;
            }

            if ((empty($value['maxwidth']) || empty($width) || $value['maxwidth'] >= $width)
                && (empty($value['maxheight']) || empty($height) || $value['maxheight'] >= $height)
            ) {
                $this->userOptions[self::SIZE_KEY] = $sizeOption;
                break;
            }
        }

        // If the resolution asked hasn't been reached, take the highest resolution we have.
        if ((!empty($width) || !empty($height)) && empty($this->userOptions[self::SIZE_KEY])) {
            $ref = array_keys($this->finderOptions[self::SIZE_KEY]);
            $this->userOptions[self::SIZE_KEY] = end($ref);
        }
    }

    /**
     * @inheritdoc
     */
    public function getDomain()
    {
        return $this->domain;
    }
}
