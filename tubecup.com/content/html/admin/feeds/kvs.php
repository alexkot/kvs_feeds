<?php
/* Developed by Kernel Team.
   http://kernel-team.com
*/
include_once('../include/setup.php');

function kvs_parse_feed($url,$feed_config)
{
	global $config;

	$feed_contents=get_page('',$url,'','',1,0,600,'');
	if (strlen($feed_contents)==0)
	{
		return null;
	}

	preg_match("|<videos base_video_url=\"(.*?)\" base_screen_url=\"(.*?)\">|is",$feed_contents,$temp);
	$base_video_url=trim($temp[1]);
	$base_screen_url=trim($temp[2]);

	preg_match_all("|<video>(.*?)</video>|is",$feed_contents,$temp);
	$items=$temp[1];

	$result=array();
	foreach ($items as $item)
	{
		$video_record=kvs_parse_item($item,$base_video_url,$base_screen_url);
		$video_record['external_key']=$video_record['video_id'];
		$result[]=$video_record;
	}

	return $result;
}

function kvs_check_feed_content($url,$feed_config)
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

	preg_match("|<videos base_video_url=\"(.*?)\" base_screen_url=\"(.*?)\">|is",$feed_contents,$temp);
	$base_video_url=$temp[1];
	$base_screen_url=$temp[2];

	preg_match_all("|<video>(.*?)</video>|is",$feed_contents,$temp);
	$items=$temp[1];

	foreach ($items as $item)
	{
		$video_record=kvs_parse_item($item,$base_video_url,$base_screen_url);
		$video_record['external_key']=$video_record['video_id'];
		return $video_record;
	}

	return null;
}

function kvs_format_feed($videos,$feed_config)
{
	global $config,$languages;

	header("Content-Type: text/xml; charset=utf-8");
	$result="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	if ($feed_config['screenshot_sources']==1)
	{
		$result.="<videos base_video_url=\"$config[project_url]/get_file/\" base_screen_url=\"$config[screen_project_url]/get_file/0/\">\n";
	} else {
		$result.="<videos base_video_url=\"$config[project_url]/get_file/\" base_screen_url=\"$config[content_url_videos_screenshots]/\">\n";
	}

	foreach ($videos as $video)
	{
		$dir_path=get_dir_by_id($video['video_id']);

		$item_result='';
		$item_result.="\t<video>\n";
		$item_result.="\t\t".kvs_format_feed_tag('id',$video['video_id'])."\n";
		$item_result.="\t\t".kvs_format_feed_tag('title',$video['title'])."\n";
		if ($feed_config['enable_localization']==1)
		{
			foreach ($languages as $language)
			{
				if ($video["title_$language[code]"]<>'')
				{
					$item_result.="\t\t".kvs_format_feed_tag("title_$language[code]",$video["title_$language[code]"])."\n";
				}
			}
		}
		$item_result.="\t\t".kvs_format_feed_tag('dir',$video['dir'])."\n";
		if ($feed_config['enable_localization']==1)
		{
			foreach ($languages as $language)
			{
				if ($video["dir_$language[code]"]<>'')
				{
					$item_result.="\t\t".kvs_format_feed_tag("dir_$language[code]",$video["dir_$language[code]"])."\n";
				}
			}
		}
		if ($video['description']<>'')
		{
			$item_result.="\t\t".kvs_format_feed_tag('description',$video['description'])."\n";
			if ($feed_config['enable_localization']==1)
			{
				foreach ($languages as $language)
				{
					if ($video["description_$language[code]"]<>'')
					{
						$item_result.="\t\t".kvs_format_feed_tag("description_$language[code]",$video["description_$language[code]"])."\n";
					}
				}
			}
		}
		$item_result.="\t\t".kvs_format_feed_tag('rating',round($video['rating']))."\n";
		$item_result.="\t\t".kvs_format_feed_tag('popularity',$video['popularity'])."\n";
		$item_result.="\t\t".kvs_format_feed_tag('post_date',$video['post_date'])."\n";
		if ($feed_config['video_content_type_id']==1 || $feed_config['video_content_type_id']==3)
		{
			$item_result.="\t\t".kvs_format_feed_tag('duration',$video['duration'])."\n";
		}
		if ($video['cs_title']<>'')
		{
			$item_result.="\t\t".kvs_format_feed_tag('content_source',$video['cs_title'])."\n";
		}
		if ($video['cs_url']<>'')
		{
			$item_result.="\t\t".kvs_format_feed_tag('content_source_url',$video['cs_url'])."\n";
		}
		if ($video['user_title']<>'')
		{
			$item_result.="\t\t".kvs_format_feed_tag('user',$video['user_title'])."\n";
		}
		if ($video['dvd_title']<>'')
		{
			$item_result.="\t\t".kvs_format_feed_tag('dvd',$video['dvd_title'])."\n";
		}
		if ($video['website_link']<>'')
		{
			$item_result.="\t\t".kvs_format_feed_tag('link',$video['website_link'])."\n";
		}
		if (is_array($video['tags']) && count($video['tags'])>0)
		{
			$item_result.="\t\t".kvs_format_feed_tag('tags',implode(',',$video['tags']))."\n";
		}
		if (is_array($video['categories']) && count($video['categories'])>0)
		{
			$item_result.="\t\t".kvs_format_feed_tag('categories',implode(',',$video['categories']))."\n";
		}
		if (is_array($video['models']) && count($video['models'])>0)
		{
			$item_result.="\t\t".kvs_format_feed_tag('models',implode(',',$video['models']))."\n";
		}
		if ($video['embed']<>'')
		{
			$item_result.="\t\t".kvs_format_feed_tag('embed',$video['embed'])."\n";
		}
		if (is_array($video['hotlink_format']))
		{
			$item_result.="\t\t<files>\n";
			$item_result.="\t\t\t<file>\n";
			$item_result.="\t\t\t\t".kvs_format_feed_tag('duration',$video['hotlink_format']['duration'])."\n";
			$item_result.="\t\t\t\t".kvs_format_feed_tag('width',$video['hotlink_format']['dimensions'][0])."\n";
			$item_result.="\t\t\t\t".kvs_format_feed_tag('height',$video['hotlink_format']['dimensions'][1])."\n";
			$item_result.="\t\t\t\t".kvs_format_feed_tag('filesize',$video['hotlink_format']['file_size'])."\n";
			if ($feed_config['video_content_type_id']==4)
			{
				$video_url="$config[project_url]/get_file/".$video['server_group_id'].'/'.$video['hotlink_format']['file_path'].'/';
				$time=date("YmdHis");
				$ahv=md5($video_url.$time.$config['ahv']);
				$item_result.="\t\t\t\t".kvs_format_feed_tag('url',$video['server_group_id'].'/'.$video['hotlink_format']['file_path']."/?time=$time&ahv=$ahv")."\n";
			} else {
				$item_result.="\t\t\t\t".kvs_format_feed_tag('url',$video['server_group_id'].'/'.$video['hotlink_format']['file_path'].'/')."\n";
			}
			$item_result.="\t\t\t</file>\n";
			$item_result.="\t\t</files>\n";
		} elseif ($video['file_url']<>'') {
			$dimensions=explode("x",$video['file_dimensions']);
			$item_result.="\t\t<files>\n";
			$item_result.="\t\t\t<file>\n";
			$item_result.="\t\t\t\t".kvs_format_feed_tag('duration',$video['duration'])."\n";
			$item_result.="\t\t\t\t".kvs_format_feed_tag('width',$dimensions[0])."\n";
			$item_result.="\t\t\t\t".kvs_format_feed_tag('height',$dimensions[1])."\n";
			$item_result.="\t\t\t\t".kvs_format_feed_tag('filesize',$video['file_size'])."\n";
			$item_result.="\t\t\t\t".kvs_format_feed_tag('url',$video['file_url'])."\n";
			$item_result.="\t\t\t</file>\n";
			$item_result.="\t\t</files>\n";
		}
		$item_result.="\t\t<screens main=\"$video[screen_main]\">\n";
		for ($i=1;$i<=$video['screen_amount'];$i++)
		{
			if ($feed_config['screenshot_sources']==1)
			{
				$hash=md5($config['cv']."$dir_path/$video[video_id]/screenshots/$i.jpg");
				$item_result.="\t\t\t".kvs_format_feed_tag('screen',"$hash/$dir_path/$video[video_id]/screenshots/$i.jpg/")."\n";
			} else {
				$item_result.="\t\t\t".kvs_format_feed_tag('screen',"$video[screen_url]/$i.jpg")."\n";
			}
		}
		$item_result.="\t\t</screens>\n";
		$item_result.="\t</video>\n";

		if ($feed_config['video_content_type_id']==1 && $video['website_link']=='')
		{
			continue;
		} elseif ($feed_config['video_content_type_id']==2 && !is_array($video['hotlink_format']) && $video['file_url']=='')
		{
			continue;
		} elseif ($feed_config['video_content_type_id']==3 && $video['embed']=='')
		{
			continue;
		}
		$result.=$item_result;
	}

	$result.="</videos>\n";

	return $result;
}

function kvs_parse_item($item,$base_video_url,$base_screen_url)
{
	global $languages;

	preg_match("|<id>(.*?)</id>|is",$item,$temp);
	$video_id=intval($temp[1]);
	preg_match("|<title>(.*?)</title>|is",$item,$temp);
	$title=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<dir>(.*?)</dir>|is",$item,$temp);
	$dir=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<description>(.*?)</description>|is",$item,$temp);
	$description=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<rating>(.*?)</rating>|is",$item,$temp);
	$rating=intval($temp[1]);
	preg_match("|<popularity>(.*?)</popularity>|is",$item,$temp);
	$popularity=intval($temp[1]);
	preg_match("|<post_date>(.*?)</post_date>|is",$item,$temp);
	$post_date=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<duration>(.*?)</duration>|is",$item,$temp);
	$duration=intval($temp[1]);
	preg_match("|<user>(.*?)</user>|is",$item,$temp);
	$user=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<content_source>(.*?)</content_source>|is",$item,$temp);
	$content_source=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<content_source_url>(.*?)</content_source_url>|is",$item,$temp);
	$content_source_url=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<dvd>(.*?)</dvd>|is",$item,$temp);
	$dvd=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<link>(.*?)</link>|is",$item,$temp);
	$website_link=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<tags>(.*?)</tags>|is",$item,$temp);
	$tags=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<categories>(.*?)</categories>|is",$item,$temp);
	$categories=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<models>(.*?)</models>|is",$item,$temp);
	$models=trim(kvs_parse_feed_tag($temp[1]));
	preg_match("|<embed>(.*?)</embed>|is",$item,$temp);
	$embed=trim(kvs_parse_feed_tag($temp[1]));

	$video_record=array();
	$video_record['video_id']=$video_id;
	$video_record['title']=$title;
	$video_record['dir']=$dir;
	$video_record['description']=$description;
	$video_record['rating']=$rating;
	$video_record['popularity']=$popularity;
	$video_record['post_date']=$post_date;
	$video_record['duration']=$duration;
	$video_record['user']=$user;
	$video_record['content_source']=$content_source;
	$video_record['content_source_url']=$content_source_url;
	$video_record['dvd']=$dvd;
	$video_record['website_link']=$website_link;
	$video_record['tags']=$tags;
	$video_record['categories']=$categories;
	$video_record['models']=$models;
	$video_record['embed_code']=$embed;

	preg_match_all("|<file>(.*?)</file>|is",$item,$temp);
	$files=$temp[1];
	foreach ($files as $file)
	{
		preg_match("|<url>(.*?)</url>|is",$file,$temp);
		$url=trim(kvs_parse_feed_tag($temp[1]));
		preg_match("|<duration>(.*?)</duration>|is",$file,$temp);
		$duration=intval($temp[1]);

		$file_record=array();
		if (strpos($url,'http://')===0)
		{
			$file_record['url']=$url;
		} else {
			$file_record['url']=$base_video_url.$url;
		}
		$file_record['duration']=$duration;
		$video_record['video_files'][]=$file_record;
		$video_record['duration']=$duration;
	}

	preg_match("|<screens main=\"(.*?)\">|is",$item,$temp);
	$main_screen=intval($temp[1]);
	$video_record['screen_main']=$main_screen;

	preg_match_all("|<screen>(.*?)</screen>|is",$item,$temp);
	$screens=$temp[1];
	foreach ($screens as $screen)
	{
		$video_record['screenshots'][]=$base_screen_url.trim(kvs_parse_feed_tag($screen));
	}

	foreach ($languages as $language)
	{
		preg_match("|<title_$language[code]>(.*?)</title_$language[code]>|is",$item,$temp);
		$title_localized=trim(kvs_parse_feed_tag($temp[1]));
		if ($title_localized!='')
		{
			$video_record["title_$language[code]"]=$title_localized;
		}

		preg_match("|<description_$language[code]>(.*?)</description_$language[code]>|is",$item,$temp);
		$description_localized=trim(kvs_parse_feed_tag($temp[1]));
		if ($description_localized!='')
		{
			$video_record["description_$language[code]"]=$description_localized;
		}

		preg_match("|<dir_$language[code]>(.*?)</dir_$language[code]>|is",$item,$temp);
		$dir_localized=trim(kvs_parse_feed_tag($temp[1]));
		if ($dir_localized!='')
		{
			$video_record["dir_$language[code]"]=$dir_localized;
		}
	}

	return $video_record;
}

function kvs_format_feed_tag($tag_name,$value)
{
	$value=str_replace("&","&amp;",$value);
	$value=str_replace(">","&gt;",$value);
	$value=str_replace("<","&lt;",$value);
	return "<$tag_name>$value</$tag_name>";
}

function kvs_parse_feed_tag($value)
{
	$value=str_replace("&lt;","<",$value);
	$value=str_replace("&gt;",">",$value);
	$value=str_replace("&amp;","&",$value);
	return $value;
}
?>