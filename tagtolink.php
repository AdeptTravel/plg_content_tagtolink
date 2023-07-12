<?php

/**
 * Tag to Link Content Plugin
 *
 * @author     Brandon J. Yaniz (joomla@adept.travel)
 * @copyright  2022 The Adept Traveler, Inc., All Rights Reserved.
 * @license    BSD 2-Clause; See LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Tags\Site\Helper\RouteHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class PlgContentTagToLink extends \Joomla\CMS\Plugin\CMSPlugin
{
  /**
   * @var    \Joomla\CMS\Application\SiteApplication
   *
   * @since  3.9.0
   */
  protected $app;

  /**
   * Plugin 
   *
   * @param   string   $context  The context of the content being passed to the plugin.
   * @param   mixed    &$row     An object with a "text" property or the string to be cloaked.
   * @param   mixed    &$params  Additional parameters.
   * @param   integer  $page     Optional page number. Unused. Defaults to zero.
   *
   * @return  void
   */
  public function onContentPrepare($context, &$article, &$params, $page = 0)
  {

    if (
      $this->app->isClient('site')
      && ($context == 'com_content.article' || $context == 'com_tags.tag')
      && $this->app->getDocument() instanceof \Joomla\CMS\Document\HtmlDocument
    ) {

      $doc = JFactory::getDocument();
      $uri = Uri::getInstance();

      $parents = $this->params->get('parents', []);

      $query  = 'SELECT `id`, `alias`, `title`, `path`';
      $query .= ' FROM #__tags';
      $query .= ' WHERE';

      if (!empty($parents)) {
        $query .= ' `parent_id` IN (' . implode(',', $parents) . ') AND';
      }

      $query .= ' published = 1';

      $db = Factory::getDbo();
      $db->setQuery($query);
      $results = $db->loadObjectList();

      // This block get's all the tags, then organizes them based on number of words/alphabetical order
      $tags = [];
      $tmp = [];

      for ($i = 0; $i < count($results); $i++) {
        $parts = explode(' ', $results[$i]->title);

        if (array_key_exists(count($parts), $tmp)) {
          $tmp[count($parts)][] = $results[$i];
        } else {
          $tmp[count($parts)][] = $results[$i];
        }
      }

      krsort($tmp);

      foreach ($tmp as $t) {
        sort($t);
        $tags = array_merge($tags, $t);
      }

      $html = $article->text;
      $howToRoute = $this->params->get('route', 'none');

      for ($i = 0; $i < count($tags); $i++) {

        $title = $tags[$i]->title;
        $link = '/index.php?option=com_tags&view=tag&parent_id=' . $tags[$i]->id;
        $needle = strtolower($title);

        if (isset($article->title) && strtolower($article->title) == $needle) {
          continue;
        }

        if ($howToRoute == 'path') {
          $link = $tags[$i]->path;
        } else if ($howToRoute == 'router') {
          $link = Route::_(RouteHelper::getTagRoute($tags[$i]->id . ':' . $tags[$i]->alias));
        }

        $link = '<a href="' . $link . '" title="' . $title . '">' . $title . '</a>';

        if (('/' . $link) != $uri->getPath() && $html != $title) {

          foreach (['p', 'li', 'h3'] as $tag) {

            $pos = 0;

            while ($pos !== false) {

              $pos = strpos($html, '<' . $tag, $pos);

              if ($pos === false) {
                break;
              }

              $pos = strpos($html, '>', $pos) + 1;
              $end = strpos($html, '</' . $tag, $pos);

              $text = substr($html, $pos, $end - $pos);
              $haystack = strtolower($text);

              $x = strpos($haystack, $needle);
              $y = $x + strlen($needle);

              // Check if tag is found in the current search text
              if ($x !== false) {
                // Check if our current start position is either 0 or is preceided by approved characters
                if ($x == 0 || in_array(substr($haystack, $x - 1, 1), [' ', '>'])) {
                  // Check if our end position is at the end of our search text, or if the characters following the match are approved.
                  if ($y == strlen($text) || in_array(substr($haystack, $y, 1), [' ', ',', '.', ':', ';'])) {
                    // Make sure that the text isn't already in a link
                    if (substr($haystack, strpos($haystack, '<', $y), 3) != '</a') {
                      $x += $pos;
                      $y += $pos;

                      $html = substr($html, 0, $x) . $link . substr($html, $y);
                      $pos = $end;
                    }
                  }
                }
              }

              $end = strpos($html, '</' . $tag, $pos);
              $pos = strpos($html, '>', $end);
            }
          }
        }
      }

      $article->text = $html;
    }
  }
}
