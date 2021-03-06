<?php

namespace WebThumbnailer\Finder;

use WebThumbnailer\Application\ConfigManager;
use WebThumbnailer\Exception\BadRulesException;
use WebThumbnailer\Exception\IOException;
use WebThumbnailer\Exception\UnsupportedDomainException;
use WebThumbnailer\Utils\DataUtils;
use WebThumbnailer\Utils\FileUtils;
use WebThumbnailer\Utils\FinderUtils;
use WebThumbnailer\Utils\UrlUtils;

/**
 * Class FinderFactory
 *
 * Find the appropriate Finder for a given URL, instantiate it and load its rules.
 *
 * @package WebThumbnailer\Finder
 */
class FinderFactory
{
    /**
     * Creates a finder object for a given URL.
     *
     * @param string $url given URL.
     *
     * @return Finder object.
     *
     * @throws BadRulesException
     * @throws IOException
     */
    public static function getFinder($url)
    {
        $domain = UrlUtils::getDomain($url);
        try {
            list($domain, $finder, $rules, $options) = self::getThumbnailMeta($domain, $url);

            $className = '\\WebThumbnailer\\Finder\\' . $finder . 'Finder';
            if (!class_exists($className)) {
                throw new UnsupportedDomainException();
            }
        } catch (UnsupportedDomainException $e) {
            $className = '\\WebThumbnailer\\Finder\\DefaultFinder';
            $rules = [];
            $options = [];
        }

        return new $className($domain, $url, $rules, $options);
    }

    /**
     * Retrieve JSON metadata for the given domains.
     *
     * @param string $inputDomain Domain to search.
     * @param string $url         Complete URL.
     *
     * @return array [domains, finder name, rules, options].
     *
     * @throws UnsupportedDomainException No rules found for the domains.
     * @throws BadRulesException          Mandatory rules not found for the domains.
     * @throws IOException
     */
    public static function getThumbnailMeta($inputDomain, $url)
    {
        // Load JSON rule files.
        $jsonFiles = ConfigManager::get('settings.rules_filename', ['rules.json']);
        $allRules = [];
        foreach ($jsonFiles as $file) {
            $allRules = array_merge($allRules, DataUtils::loadJson(FileUtils::RESOURCES_PATH . $file));
        }

        $domain = null;

        foreach ($allRules as $value) {
            self::checkMetaFormat($value);

            $domainFound = false;
            foreach ($value['domains'] as $domain) {
                if (strpos($inputDomain, $domain) !== false) {
                    $domainFound = true;
                    break;
                }
            }

            if (!$domainFound) {
                continue;
            }

            if (!empty($value['url_exclude'])) {
                preg_match(FinderUtils::buildRegex($value['url_exclude'], 'i'), $url, $match);
                if (!empty($match)) {
                    continue;
                }
            }

            if (!empty($value['url_require'])) {
                preg_match(FinderUtils::buildRegex($value['url_require'], 'i'), $url, $match);
                if (empty($match)) {
                    continue;
                }
            }

            $value['rules'] = !empty($value['rules']) ? $value['rules'] : [];
            $value['options'] = !empty($value['options']) ? $value['options'] : [];
            return [$domain, $value['finder'], $value['rules'], $value['options']];
        }

        throw new UnsupportedDomainException();
    }

    /**
     * Make sure that mandatory directives are present in the metadata.
     *
     * @param array $rules JSON directives.
     *
     * @throws BadRulesException Mandatory rules not found for the domains.
     */
    public static function checkMetaFormat($rules)
    {
        $mandatoryDirectives = ['domains', 'finder'];
        foreach ($mandatoryDirectives as $mandatoryDirective) {
            if (empty($rules[$mandatoryDirective])) {
                throw new BadRulesException();
            }
        }
    }
}
