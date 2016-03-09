<?php
/* Developed by Kernel Team.
   http://kernel-team.com
*/
include_once('../include/setup.php');
include_once('../include/gallery_parser.php');

function rss_parse_feed($url,$feed_config)
{
	global $config;

	$feed_contents=get_page('',$url,'','',1,0,600,'');
	if (strlen($feed_contents)==0)
	{
		return null;
	}

	preg_match_all("|<item>(.*?)</item>|is",$feed_contents,$temp);
	$items=$temp[1];

	$result=array();
	foreach ($items as $item)
	{
		$video_record=rss_parse_item($item);
		$video_record['external_key']=$video_record['website_link'];
		$result[]=$video_record;
	}

	return $result;
}

function rss_check_feed_content($url,$feed_config)
{
	global $config;

	if (strpos($url,'?')===false)
	{
		$url.='?kvs_test_feed=true';
	} else {
		$url.='&kvs_test_feed=true';
	}
	$feed_contents=get_page('',$url,'','',1,0,600,'');
	if (strlen($feed_contents)==0)
	{
		return null;
	}

	preg_match_all("|<item>(.*?)</item>|is",$feed_contents,$temp);
	$items=$temp[1];

	$result=array();
	foreach ($items as $item)
	{
		$video_record=rss_parse_item($item);
		$video_record['external_key']=$video_record['website_link'];
		return $video_record;
	}

	return null;
}

function rss_parse_item($item)
{
	preg_match("|<title>(.*?)</title>|is",$item,$temp);
	$title=trim(rss_parse_feed_tag($temp[1]));
	preg_match("|<description>(.*?)</description>|is",$item,$temp);
	$description=trim(rss_parse_feed_tag($temp[1]));
	preg_match("|<link>(.*?)</link>|is",$item,$temp);
	$website_link=trim(rss_parse_feed_tag($temp[1]));
	preg_match("|<pubDate>(.*?)</pubDate>|is",$item,$temp);
	$post_date=trim(rss_parse_feed_tag($temp[1]));

	$video_record=array();
	$video_record['title']=$title;
	$video_record['description']=$description;
	$video_record['post_date']=$post_date;
	$video_record['website_link']=$website_link;
	$video_record['duration']=1;

	if ($video_record['post_date']=='')
	{
		$video_record['post_date']=date("Y-m-d H:i:s");
	}
	if ($video_record['website_link']<>'')
	{
		$urls=parse_gallery($video_record['website_link']);
		$video_record['video_files']=array();
		foreach ($urls as $url)
		{
			$file_record=array();
			$file_record['url']=$url;
			$video_record['video_files'][]=$file_record;
		}
	}

	return $video_record;
}

function rss_parse_feed_tag($value)
{
	if (strpos($value,"<![CDATA[")!==false)
	{
		$value=str_replace("<![CDATA[","",$value);
		$value=str_replace("]]>","",$value);
	}
	$value=str_replace("&lt;","<",$value);
	$value=str_replace("&gt;",">",$value);
	$value=str_replace("&amp;","&",$value);
	return $value;
}
?>