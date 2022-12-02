<?php 
# ***** BEGIN LICENSE BLOCK *****
# Copyright (c) 2005 Olivier Meunier and contributors. All rights reserved.
# Copyright (c) 2007 Vincent Untz
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
# ***** END LICENSE BLOCK *****
#
# This plugin is based on:
#   rs.extension.php (from DotClear)
#   the Highliting Search plugin for DotClear 1.x (by Bertrand Carlier)
if (!defined('DC_RC_PATH')) return;

$core->addBehavior('coreBlogGetPosts',array('highlightSearch','coreBlogGetPosts'));
$core->addBehavior('coreBlogGetComments',array('highlightSearch','coreBlogGetComments'));

$core->addBehavior('corePostSearch',array('highlightSearch','corePostSearch'));
$core->tpl->addValue('SysSearchString',array('highlightSearch','SysSearchString'));

highlightSearch::init();

class highlightSearch
{
	private static $highlight_words = array ();

	public static function init ()
	{
		if (isset($_GET['q'])) {
			self::$highlight_words = explode(" ", $_GET['q']);
		} else {
			$keywords = self::getKeywords();
			if ($keywords != false) {
				self::$highlight_words = explode(" ", $keywords);
			}
		}
	}

	private static function getKeywords()
	{

		$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";

		$var_sep =  array("&amp;", "&", "|");
		$word_sep = array( "+", " ", "/");
		$match = array(
		"ara", "busca", "pesquis", "search", "srch", "seek", "zoek", "result", "szuka", "cherch", "such", "find",
		"trouve", "trova", "pursuit", "keres", "katalogus", "alltheinternet.com", "mamma.com", "baidu.com", "heureka.hu",
		"kartoo.com", "ask.com", "aport.ru", "google", "yahoo"
		);
		
		foreach ($match as $key) 
		{
			// if string occurs at the beginning strpos() returns integer 0, if it can't be
			// found at all, however, it returns boolean false => definition required which
			// considers 0 as true
			$is_search = (strpos(strtolower($ref), $key) !== false) ? true : false;
			if ($is_search) break;
		}
		
		if ($is_search)
		{
			
			$ref = urldecode($ref);
			$is_query = strpos($ref, "?");
			$ref = ($is_query !== false) ? substr($ref, ++$is_query) : substr($ref, (strpos($ref, "://") + 3));
			$get_vars = self::bbc_get_sep($ref, $var_sep);
			$raw_search = self::bbc_get_search($get_vars);
			//$raw search contient la phrase recherchée
			return $raw_search;
		}
		return false;
	}
	
	private static function bbc_get_sep($query, $array) 
	{
		// puts the query into an array
		$lower = strtolower($query);
		
		foreach ($array as $match) 
		{
			$has_sep = (strpos($lower, $match) !== false) ? true : false;
			$pool = $has_sep ? explode($match, $lower) : array($lower);
			
			for ($i = 0, $max = count($pool); $i < $max; $i++) 
			{
				$pool[$i] = trim(preg_replace("?[\\\"\$]+?", "", $pool[$i]));
				$pool[$i] = preg_replace("?^[<>@\^\!\?/\(\)\[\]\{\}|+*~#;,.:_\-]+?", "", $pool[$i]);
				$pool[$i] = preg_replace("?[<>@\^\!\?/\(\)\[\]\{\}|+*~#;,.:_\-]+\$?", "", $pool[$i]);
				
				if (empty($pool[$i]) || (strlen($pool[$i]) < 2)) 
				{
					unset($pool[$i]);
					continue;
				}
			}
			if ($has_sep) return array_values($pool);
		}
		return array_values($pool);
	}
	
	private static function bbc_get_search($array) 
	{
		// turns variable assignments to an associative array
		$query = array(
		"^as_(ep|o|e)?q=", "^q(_(a(ll|ny)|phrase|not)|t|u(ery)?)?=", "^s(u|2f|p\-q|earch(_?for)?|zukaj)?=",
		"^k(w|e(reses|y(word)?s?))=", "^b(egriff|uscar?)=", "^w(d|ords?)?=", "^te(rms?|xt)=", "^mi?t=", "^heureka=",
		"^p=", "^r(eq)?=", "/search/web/", "^v[aeop]=", "^next=\/search\?q="
		);
		
		foreach ($array as $string) 
		{
			$string = strtolower(urldecode($string));
			// skip empty GET variables
			if (substr($string, (strlen($string) - 1)) == "=") continue;
			
			foreach ($query as $key) 
			{
				preg_match(":$key:", $string, $matches);
				if (count($matches) == 0) continue;
				
				$par = $matches[0];
				$pos = strpos($string, $par);
				$term = substr($string, ($pos + strlen($par)));
				$term = strip_tags(stripslashes($term));
				
				if (strlen($term) < 3) continue;
				return $term;
			}
		}
		return false;
	}

	public static function corePostSearch($core)
	{
		/* we're losing the potential filters that the theme can set
		   (ie, remove_html, cut_string, lower_case, upper_case) and we
		   assume the user always want escape_html */
		$value = isset($GLOBALS['_search']) ? html::escapeHTML($GLOBALS['_search']) : '';
		$GLOBALS['_search_highlighted'] = self::highlight_all($value);
	}

	public static function coreBlogGetPosts($rs)
	{
		$rs->extend('highlightSearchPost');
	}
	
	public static function coreBlogGetComments($rs)
	{
		$rs->extend('highlightSearchComment');
	}

        public function SysSearchString($attr)
        {
		$s = isset($attr['string']) ? $attr['string'] : '%1$s';

		/* note: we don't put filters here: we'll have additional html
		   for the highlight, so filters will mess this up */
		return '<?php if (isset($_search)) { echo sprintf(__(\''.$s.'\'),$_search_highlighted,$_search_count);} ?>';
        }

	private static function highlight($word, $text, $number)
	{
		$w = preg_quote($word, '%');

		if ($word == '+') $w = '\+';
		if ($word == '*') $w = '\*';

		return preg_replace("%(?!<.*?)(".$w.")(?![^<>]*?>)%si", "<span class=\"highlight$number\">\\1</span>", $text);
	}

	public static function highlight_all($text)
	{
		foreach(self::$highlight_words as $key => $word) {
			$text = self::highlight($word,$text,$key % 5);
		}
		return $text;
	}
}

class highlightSearchPost extends rsExtPost
{
	public static function getContent($rs,$absolute_urls=false)
	{
		$c = parent::getContent($rs,$absolute_urls);
		return highlightSearch::highlight_all($c);
	}
	
	public static function getExcerpt($rs,$absolute_urls=false)
	{
		$c = parent::getExcerpt($rs,$absolute_urls);
		return highlightSearch::highlight_all($c);
	}
}

class highlightSearchComment extends rsExtComment
{
	public static function getContent($rs,$absolute_urls=false)
	{
		$c = parent::getContent($rs,$absolute_urls);
		return highlightSearch::highlight_all($c);
	}
}
?>
