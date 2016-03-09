<?php
/* Developed by Kernel Team.
   http://kernel-team.com
*/

function smartscripts_parse_feed($url,$feed_config)
{
	global $config;

	$feed_contents=get_page('',$url,'','',1,0,600,'');
	if (strlen($feed_contents)==0)
	{
		return null;
	}

	preg_match_all("|<video>(.*?)</video>|is",$feed_contents,$temp);
	$items=$temp[1];

	$result=array();
	foreach ($items as $item)
	{
		$video_record=smartscripts_parse_item($item);
		$video_record['external_key']=$video_record['video_id'];
		$result[]=$video_record;
	}

	return $result;
}

function smartscripts_check_feed_content($url,$feed_config)
{
	global $config;

	$feed_contents=get_page('',$url,'','',1,0,600,'');
	if (strlen($feed_contents)==0)
	{
		return null;
	}

	preg_match_all("|<video>(.*?)</video>|is",$feed_contents,$temp);
	$items=$temp[1];

	$result=array();
	foreach ($items as $item)
	{
		$video_record=smartscripts_parse_item($item);
		$video_record['external_key']=$video_record['video_id'];
		return $video_record;
	}

	return null;
}

function smartscripts_parse_item($item)
{
	preg_match("|<id>(.*?)</id>|is",$item,$temp);
	$video_id=intval(smartscripts_parse_feed_tag($temp[1]));
	preg_match("|<title>(.*?)</title>|is",$item,$temp);
	$title=trim(smartscripts_parse_feed_tag($temp[1]));
	preg_match("|<description>(.*?)</description>|is",$item,$temp);
	$description=trim(smartscripts_parse_feed_tag($temp[1]));
	preg_match("|<tags>(.*?)</tags>|is",$item,$temp);
	$tags=trim(smartscripts_parse_feed_tag($temp[1]));
	preg_match("|<paysite>(.*?)</paysite>|is",$item,$temp);
	$paysite=trim(smartscripts_parse_feed_tag($temp[1]));
	preg_match("|<clip_url>(.*?)</clip_url>|is",$item,$temp);
	$clip_url=trim(smartscripts_parse_feed_tag($temp[1]));
	preg_match("|<screen_url>(.*?)</screen_url>|is",$item,$temp);
	$screen_url=trim(smartscripts_parse_feed_tag($temp[1]));

	$video_record=array();
	$video_record['video_id']=$video_id;
	$video_record['title']=$title;
	$video_record['description']=$description;
	$video_record['duration']=0;
	$video_record['tags']=$tags;
	$video_record['content_source']=$paysite;

	preg_match_all("|<clip>(.*?)</clip>|is",$item,$temp);
	$clips=$temp[1];
	foreach ($clips as $clip)
	{
		preg_match("|<flv>(.*?)</flv>|is",$clip,$temp);
		$flv=trim(smartscripts_parse_feed_tag($temp[1]));
		preg_match("|<mp4>(.*?)</mp4>|is",$clip,$temp);
		$mp4=trim(smartscripts_parse_feed_tag($temp[1]));
		preg_match("|<duration>(.*?)</duration>|is",$clip,$temp);
		$duration=intval(smartscripts_parse_feed_tag($temp[1]));
		preg_match_all("|<screen>(.*?)</screen>|is",$clip,$temp);
		$screens=$temp[1];
		foreach ($screens as $k=>$v)
		{
			$screens[$k]=$screen_url.trim(smartscripts_parse_feed_tag($v));
			$video_record['screenshots'][]=$screens[$k];
		}

		$clip_record=array();
		if ($mp4!='')
		{
			$clip_record['url']=$clip_url.$mp4;
		} else {
			$clip_record['url']=$clip_url.$flv;
		}
		$clip_record['duration']=$duration;
		$clip_record['screenshots']=$screens;
		$video_record['video_files'][]=$clip_record;
		$video_record['duration']+=$duration;
	}

	return $video_record;
}

function smartscripts_parse_feed_tag($value)
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