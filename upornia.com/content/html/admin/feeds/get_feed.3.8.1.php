<?php
/* Developed by Kernel Team.
   http://kernel-team.com
*/

$external_id=$_GET['external_id'];
if ($external_id=='')
{
	header("HTTP/1.0 404 Not found");
	echo "No external ID is specified";die;
}

include_once('../include/setup.php');
include_once('../include/setup_db.php');
include_once('../include/functions.php');
include_once('../include/functions_base.php');
include_once('../include/placeholder.php');

$feed=mr2array_single(sql_pr("select * from $config[tables_prefix]videos_feeds_export where external_id=?",$external_id));
if (count($feed)<2)
{
	header("HTTP/1.0 404 Not found");
	echo "Feed with external ID \"$external_id\" is not available";die;
}

if ($feed['status_id']==0)
{
	header("HTTP/1.0 403 Forbidden");
	echo "Feed with external ID \"$external_id\" is not active";die;
}

$post_date_selector='post_date';
if ($config['relative_post_dates']=="true")
{
	$post_date_selector="(case when $config[tables_prefix]videos.relative_post_date!=0 then date_add(now(), interval $config[tables_prefix]videos.relative_post_date-1 day) else $config[tables_prefix]videos.post_date end)";
}

$feed_options=@unserialize($feed['options']);

$feed_format=trim($_GET['feed_format']);
$locale=trim($_GET['locale']);
$password=trim($_GET['password']);
$limit=intval($_GET['limit']);
$start=intval($_GET['start']);
$days=intval($_GET['days']);
$min_duration=intval($_GET['min_duration']);
$max_duration=intval($_GET['max_duration']);
$screenshot_format=trim($_GET['screenshot_format']);
$video_format_standard=trim($_GET['video_format_standard']);
$video_format_premium=trim($_GET['video_format_premium']);
$csv_separator=trim($_GET['csv_separator']);
$csv_columns=trim($_GET['csv_columns']);
$sorting=trim($_GET['sorting']);
$player_skin=trim($_GET['player_skin']);
$player_autoplay=trim($_GET['player_autoplay']);
$player_width=intval($_GET['player_width']);
$player_height=intval($_GET['player_height']);
$sponsor_filter=trim($_GET['sponsor']);
$category_filter=trim($_GET['category']);
$tag_filter=trim($_GET['tag']);
$model_filter=trim($_GET['model']);
if ($config['dvds_mode']=='channels')
{
	$dvd_filter=trim($_GET['channel']);
}

$screenshot_formats=mr2array_list(sql("select size from $config[tables_prefix]formats_screenshots where group_id=1"));
if ($feed_options['enable_screenshot_sources']==1)
{
	$screenshot_formats[]='source';
}

$video_formats_standard=array();
$video_formats_premium=array();
if ($feed_options['video_content_type_id']==2 || $feed_options['video_content_type_id']==4)
{
	$video_formats=mr2array(sql("select title, postfix, video_type_id from $config[tables_prefix]formats_videos where access_level_id=0"));
	foreach ($video_formats as $format)
	{
		if ($format['video_type_id']==0 && in_array($feed_options['video_type_id'],array(0,1,3,4)))
		{
			$video_formats_standard[]=$format['title'];
		}
		if ($format['video_type_id']==1 && in_array($feed_options['video_type_id'],array(0,2)))
		{
			$video_formats_premium[]=$format['title'];
		}
	}
}
$languages=mr2array(sql("select * from $config[tables_prefix]languages order by title asc"));

$player_data_embed=@unserialize(file_get_contents("$config[project_path]/admin/data/player/embed/config.dat"));

if ($feed['password']<>'' && $feed['password']<>$password)
{
	header("Content-Type: text/plain");
	echo "ERROR: password is not specified or invalid\n\n";
	echo "================================================================================\n\n";
	print_doc($feed);
	die;
}

if ($_GET['action']=='get_deleted' || $_GET['action']=='get_deleted_ids' || $_GET['action']=='get_deleted_urls')
{
	$where_days="";
	if (intval($_GET['days'])>0)
	{
		$where_days="and deleted_date>'".date("Y-m-d H:i:s",mktime(0,0,0,date("m"),date("d")-intval($_GET['days']),date("Y")))."'";
	}
	$selector="object_id";
	if ($_GET['action']=='get_deleted_urls')
	{
		$selector="url";
	}
	header("Content-Type: text/plain");
	$data=mr2array_list(sql("select $selector from $config[tables_prefix]deleted_content where object_type_id=1 $where_days order by deleted_date asc"));
	foreach ($data as $item)
	{
		if ($item!='')
		{
			if ($selector=="url")
			{
				echo str_replace("www.","",$item)."\n";
				echo str_replace("https://","https://www.",str_replace("http://","http://www.",str_replace("www.","",$item)))."\n";
			} else {
				echo "$item\n";
			}
		}
	}
	die;
}

if (!in_array($feed_format,array('csv','kvs')))
{
	header("Content-Type: text/plain");
	print_doc($feed);
	die;
}

if ($limit==0 || $limit>$feed['max_limit'])
{
	$limit=$feed['max_limit'];
}

if ($feed_options['enable_localization']==1 && $locale!='')
{
	$valid_language=false;
	foreach ($languages as $language)
	{
		if ($locale==$language['code'])
		{
			$valid_language=true;
		}
	}
	if (!$valid_language)
	{
		header("Content-Type: text/plain");
		echo "ERROR: locale is invalid\n\n";
		echo "================================================================================\n\n";
		print_doc($feed);
		die;
	}
}

if ($screenshot_format<>'')
{
	if (!in_array($screenshot_format,$screenshot_formats))
	{
		header("Content-Type: text/plain");
		echo "ERROR: screenshot format is invalid\n\n";
		echo "================================================================================\n\n";
		print_doc($feed);
		die;
	}
} else {
	$screenshot_format=$screenshot_formats[0];
}

if ($video_format_standard<>'')
{
	if (!in_array($video_format_standard,$video_formats_standard))
	{
		header("Content-Type: text/plain");
		echo "ERROR: video format for standard videos is invalid\n\n";
		echo "================================================================================\n\n";
		print_doc($feed);
		die;
	}
} elseif (count($video_formats_standard)>0)
{
	$video_format_standard=$video_formats_standard[0];
}
if ($video_format_standard<>'')
{
	foreach ($video_formats as $format)
	{
		if ($format['video_type_id']==0 && $format['title']==$video_format_standard)
		{
			$video_format_standard=$format['postfix'];
			break;
		}
	}
}

if ($video_format_premium<>'')
{
	if (!in_array($video_format_premium,$video_formats_premium))
	{
		header("Content-Type: text/plain");
		echo "ERROR: video format for premium videos is invalid\n\n";
		echo "================================================================================\n\n";
		print_doc($feed);
		die;
	}
} elseif (count($video_formats_premium)>0)
{
	$video_format_premium=$video_formats_premium[0];
}
if ($video_format_premium<>'')
{
	foreach ($video_formats as $format)
	{
		if ($format['video_type_id']==1 && $format['title']==$video_format_premium)
		{
			$video_format_premium=$format['postfix'];
			break;
		}
	}
}

if ($sorting<>'')
{
	$sorting_array=explode(' ',$sorting);
	if (!in_array($sorting_array[0],array('video_id','rating','popularity','duration','post_date')))
	{
		header("Content-Type: text/plain");
		echo "ERROR: sorting method is invalid\n\n";
		echo "================================================================================\n\n";
		print_doc($feed);
		die;
	}
	if (!in_array($sorting_array[1],array('','asc','desc')))
	{
		header("Content-Type: text/plain");
		echo "ERROR: sorting direction is invalid\n\n";
		echo "================================================================================\n\n";
		print_doc($feed);
		die;
	}
	if ($sorting_array[0]=='popularity')
	{
		$sorting_array[0]='video_viewed';
	}
	if ($sorting_array[1]=='')
	{
		$sorting_array[1]='desc';
	}
	if ($sorting_array[0]=='post_date')
	{
		$sorting="$post_date_selector $sorting_array[1], video_id $sorting_array[1]";
	} else {
		$sorting="$config[tables_prefix]videos.$sorting_array[0] $sorting_array[1]";
	}
} else {
	$sorting="$post_date_selector desc, video_id desc";
}

if ($player_skin<>'')
{
	if (!in_array($player_skin,array('black','white')))
	{
		header("Content-Type: text/plain");
		echo "ERROR: player skin is invalid\n\n";
		echo "================================================================================\n\n";
		print_doc($feed);
		die;
	}
}

if ($player_autoplay<>'')
{
	if (!in_array($player_autoplay,array('true','false')))
	{
		header("Content-Type: text/plain");
		echo "ERROR: player autostart flag is invalid\n\n";
		echo "================================================================================\n\n";
		print_doc($feed);
		die;
	}
}

if ($csv_columns<>'')
{
	$csv_columns=explode('|',$csv_columns);
	$allowed_columns=array('id','title','dir','description','rating','popularity','post_date','user','content_source','content_source_url','dvd','link','categories','tags','models','release_year','duration','width','height','filesize','url','embed','screenshots_prefix','main_screenshot','main_screenshot_number','screenshots');
	if ($feed_options['enable_localization']==1)
	{
		foreach ($languages as $language)
		{
			$allowed_columns[]="title_$language[code]";
			$allowed_columns[]="description_$language[code]";
			$allowed_columns[]="dir_$language[code]";
		}
	}
	if ($feed_options['enable_custom_fields']==1)
	{
		$allowed_columns[]='custom1';
		$allowed_columns[]='custom2';
		$allowed_columns[]='custom3';
	}
	foreach ($csv_columns as $k=>$csv_column)
	{
		if ($csv_column=='')
		{
			unset($csv_columns[$k]);
			continue;
		}
		if (strpos($csv_column,'static:')!==0)
		{
			if (!in_array($csv_column,$allowed_columns))
			{
				header("Content-Type: text/plain");
				echo "ERROR: csv_columns option refers unknown column \"$csv_column\"\n\n";
				echo "================================================================================\n\n";
				print_doc($feed);
				die;
			}
		}
	}
}

$where=" and video_id >= $start";
if ($feed_options['video_type_id']==1)
{
	$where.=' and (is_private=0 or is_private=1)';
} elseif ($feed_options['video_type_id']==2)
{
	$where.=' and (is_private=2)';
} elseif ($feed_options['video_type_id']==3)
{
	$where.=' and is_private=0';
} elseif ($feed_options['video_type_id']==4)
{
	$where.=' and is_private=1';
}

if ($feed_options['enable_localization']==1 && $locale!='')
{
	$where.=" and $config[tables_prefix]videos.title_$locale!=''";
}

if (is_array($config['advanced_filtering']))
{
	if (in_array('upload_zone',$config['advanced_filtering']) && $feed_options['with_upload_zone_site']==1)
	{
		$where.=' and af_upload_zone=0';
	}
}

if ($days>0)
{
	$date_passed_from=date("Y-m-d",mktime(0,0,0,date("m"),date("d")-$days+1,date("Y")));
	$where.=" and $post_date_selector>='$date_passed_from'";
}

if ($min_duration>0)
{
	$where.=" and duration>=$min_duration";
}
if ($max_duration>0)
{
	$where.=" and duration<=$max_duration";
}
if ($feed_options['with_rotation_finished']==1)
{
	$where.=" and rs_completed=1";
}

if ($sponsor_filter<>'')
{
	$content_source_id=mysql_result(sql_pr("select content_source_id from $config[tables_prefix]content_sources where title=?",$sponsor_filter),0);
	if ($content_source_id>0)
	{
		$where.=" and $config[tables_prefix]videos.content_source_id=$content_source_id";
	}
}
if ($dvd_filter<>'')
{
	$dvd_id=mysql_result(sql_pr("select dvd_id from $config[tables_prefix]dvds where title=?",$dvd_filter),0);
	if ($dvd_id>0)
	{
		$where.=" and $config[tables_prefix]videos.dvd_id=$dvd_id";
	}
}

$has_advanced_filter=0;
if ($category_filter<>'')
{
	$category_id=mysql_result(sql_pr("select category_id from $config[tables_prefix]categories where title=?",$category_filter),0);
	if ($category_id>0)
	{
		$where.=" and video_id in (select video_id from $config[tables_prefix]categories_videos where category_id=$category_id)";
		$has_advanced_filter=1;
	}
}
if ($tag_filter<>'')
{
	$tag_id=mysql_result(sql_pr("select tag_id from $config[tables_prefix]tags where tag=?",$tag_filter),0);
	if ($tag_id>0)
	{
		$where.=" and video_id in (select video_id from $config[tables_prefix]tags_videos where tag_id=$tag_id)";
		$has_advanced_filter=1;
	}
}
if ($model_filter<>'')
{
	$model_id=mysql_result(sql_pr("select model_id from $config[tables_prefix]models where title=?",$model_filter),0);
	if ($model_id>0)
	{
		$where.=" and video_id in (select video_id from $config[tables_prefix]models_videos where model_id=$model_id)";
		$has_advanced_filter=1;
	}
}

$load_type_ids="1,4";
if ($feed_options['video_content_type_id']==1)
{
	$load_type_ids="1,2,3,4,5";
} elseif ($feed_options['video_content_type_id']==2)
{
	$load_type_ids="1,2,4";
} elseif ($feed_options['video_content_type_id']==3)
{
	$load_type_ids="1,2,3,4";
} elseif ($feed_options['video_content_type_id']==4)
{
	$load_type_ids="1,2,4";
}

$now_date=date("Y-m-d H:i:s");
$post_date_filter="and post_date<='$now_date'";
if ($feed_options['enable_future_dates']==1)
{
	$post_date_filter="";
}

$localization_columns="";
if ($feed_options['enable_localization']==1)
{
	foreach ($languages as $language)
	{
		$localization_columns.="$config[tables_prefix]videos.title_$language[code], $config[tables_prefix]videos.description_$language[code], $config[tables_prefix]videos.dir_$language[code], $config[tables_prefix]content_sources.title_$language[code] as cs_title_$language[code], $config[tables_prefix]dvds.title_$language[code] as dvd_title_$language[code], ";
	}
}
$query="SELECT
			$config[tables_prefix]content_sources.title as cs_title,
			$config[tables_prefix]content_sources.url as cs_url,
			$config[tables_prefix]dvds.title as dvd_title,
			$config[tables_prefix]users.username as user_title,
			$config[tables_prefix]videos.video_id,
			$config[tables_prefix]videos.load_type_id,
			$config[tables_prefix]videos.server_group_id,
			$config[tables_prefix]videos.is_private,
			$config[tables_prefix]videos.title,
			$config[tables_prefix]videos.description,
			$localization_columns
			$config[tables_prefix]videos.dir,
			$config[tables_prefix]videos.duration,
			$config[tables_prefix]videos.file_url,
			$config[tables_prefix]videos.file_dimensions,
			$config[tables_prefix]videos.file_size,
			$config[tables_prefix]videos.file_formats,
			$config[tables_prefix]videos.embed as embed_code_temp,
			$config[tables_prefix]videos.screen_amount,
			$config[tables_prefix]videos.screen_main,
			$config[tables_prefix]videos.release_year,
			$config[tables_prefix]videos.custom1,
			$config[tables_prefix]videos.custom2,
			$config[tables_prefix]videos.custom3,
			($config[tables_prefix]videos.rating/$config[tables_prefix]videos.rating_amount) as rating,
			$config[tables_prefix]videos.video_viewed as popularity,
			$post_date_selector as post_date
		FROM
			$config[tables_prefix]videos
			left join $config[tables_prefix]content_sources on $config[tables_prefix]videos.content_source_id=$config[tables_prefix]content_sources.content_source_id
			left join $config[tables_prefix]dvds on $config[tables_prefix]videos.dvd_id=$config[tables_prefix]dvds.dvd_id
			left join $config[tables_prefix]users on $config[tables_prefix]videos.user_id=$config[tables_prefix]users.user_id
		WHERE $config[tables_prefix]videos.status_id=1 $post_date_filter and relative_post_date<=0 and load_type_id in ($load_type_ids) $where order by $sorting LIMIT $limit";

if ($has_advanced_filter==1 && $feed['cache']>0)
{
	$cache_dir="$config[project_path]/admin/data/engine/feeds_info";
	$hash=md5($query);

	$has_cached_version=0;
	if (is_file("$cache_dir/$hash[0]$hash[1]/$hash.dat") && time()-filectime("$cache_dir/$hash[0]$hash[1]/$hash.dat")<$feed['cache'])
	{
		$data=unserialize(file_get_contents("$cache_dir/$hash[0]$hash[1]/$hash.dat"));
		if (is_array($data))
		{
			$has_cached_version=1;
		}
	}
	if ($has_cached_version==0)
	{
		$data=mr2array(sql_pr($query));
		if (!is_dir("$cache_dir")) {mkdir("$cache_dir",0777);chmod("$cache_dir",0777);}
		if (!is_dir("$cache_dir/$hash[0]$hash[1]")) {mkdir("$cache_dir/$hash[0]$hash[1]",0777);chmod("$cache_dir/$hash[0]$hash[1]",0777);}
		$fp=fopen("$cache_dir/$hash[0]$hash[1]/$hash.dat","w");
		flock($fp,LOCK_EX);
		fwrite($fp,serialize($data));
		fclose($fp);
	}
} else {
	$data=mr2array(sql_pr($query));
}

$website_ui_data=@unserialize(file_get_contents("$config[project_path]/admin/data/system/website_ui_params.dat"));
$pattern=$website_ui_data['WEBSITE_LINK_PATTERN'];

$affiliate_str='';
if ($feed['affiliate_param_name']<>'' && $_REQUEST[$feed['affiliate_param_name']]<>'')
{
	$affiliate_str="?$feed[affiliate_param_name]=".$_REQUEST[$feed['affiliate_param_name']];
}

foreach ($data as $k=>$video)
{
	$video_id=$video['video_id'];
	$dir_path=get_dir_by_id($video_id);
	$video_formats=get_video_formats($video_id,$video['file_formats']);

	$data[$k]['screen_url']="$dir_path/$video_id/$screenshot_format";

	if ($feed_options['enable_localization']==1 && $locale!='')
	{
		if ($video["title_$locale"]!='')
		{
			$data[$k]['title']=$video["title_$locale"];
			$video['title']=$video["title_$locale"];
		}
		if ($video["dir_$locale"]!='')
		{
			$data[$k]['dir']=$video["dir_$locale"];
			$video['dir']=$video["dir_$locale"];
		}
		if ($video["description_$locale"]!='')
		{
			$data[$k]['description']=$video["description_$locale"];
			$video['description']=$video["description_$locale"];
		}
		if ($video["cs_title_$locale"]!='')
		{
			$data[$k]['cs_title']=$video["cs_title_$locale"];
			$video['cs_title']=$video["cs_title_$locale"];
		}
		if ($video["dvd_title_$locale"]!='')
		{
			$data[$k]['dvd_title']=$video["dvd_title_$locale"];
			$video['dvd_title']=$video["dvd_title_$locale"];
		}
	}

	if ($pattern<>'')
	{
		$data[$k]['website_link']=$config['project_url'].'/'.str_replace("%ID%",$video_id,str_replace("%DIR%",$video['dir'],$pattern)).$affiliate_str;
		if ($feed_options['enable_localization']==1 && $locale!='')
		{
			$satellites=mr2array(sql("select * from $config[tables_prefix]admin_satellites"));
			foreach ($satellites as $satellite)
			{
				$satellite_website_ui_data=@unserialize($satellite['website_ui_data']);
				if ($satellite_website_ui_data['locale']==$locale)
				{
					$data[$k]['website_link']=$satellite['project_url'].'/'.str_replace("%ID%",$video_id,str_replace("%DIR%",$video['dir'],$satellite_website_ui_data['WEBSITE_LINK_PATTERN'])).$affiliate_str;
					break;
				}
			}
		}
	}

	if ($feed_options['enable_categories']==1)
	{
		$data[$k]['categories']=get_video_categories($video_id,$feed['cache'],($feed_options['enable_localization']==1 && $locale!='') ? $locale : "");
	}
	if ($feed_options['enable_tags']==1)
	{
		$data[$k]['tags']=get_video_tags($video_id,$feed['cache'],($feed_options['enable_localization']==1 && $locale!='') ? $locale : "");
	}
	if ($feed_options['enable_models']==1)
	{
		$data[$k]['models']=get_video_models($video_id,$feed['cache'],($feed_options['enable_localization']==1 && $locale!='') ? $locale : "");
	}

	if ($feed_options['video_content_type_id']==2 || $feed_options['video_content_type_id']==4)
	{
		if ($video['load_type_id']==1 || $video['load_type_id']==4)
		{
			if ($video['is_private']==0 || $video['is_private']==1)
			{
				if ($video_format_standard<>'')
				{
					foreach ($video_formats as $format_rec)
					{
						if ($format_rec['postfix']==$video_format_standard)
						{
							$data[$k]['hotlink_format']=$format_rec;
							break;
						}
					}
				}
			} elseif ($video['is_private']==2)
			{
				if ($video_format_premium<>'')
				{
					foreach ($video_formats as $format_rec)
					{
						if ($format_rec['postfix']==$video_format_premium)
						{
							$data[$k]['hotlink_format']=$format_rec;
							break;
						}
					}
				}
			}
			if (!isset($data[$k]['hotlink_format']))
			{
				unset($data[$k]);
			}
		}
	}
	if (isset($data[$k]) && in_array($feed_options['video_content_type_id'],array(2,3,4)))
	{
		if ($video['load_type_id']==3)
		{
			$data[$k]['embed']=$video['embed_code_temp'];
		} else {
			$video_width='';
			$video_height='';
			$inc_height=0;
			if ($player_data_embed['controlbar']==0)
			{
				$inc_height=25;
			}

			if ($video['load_type_id']==2)
			{
				$dimensions=explode("x",$video['file_dimensions']);
				if ($player_width>0 && $player_height>0)
				{
					$video_width=$player_width;
					$video_height=$player_height;
				} elseif ($player_width>0)
				{
					$video_width=$player_width;
					$video_height=ceil($dimensions[1]/$dimensions[0]*$player_width+$inc_height);
				} elseif ($player_height>0)
				{
					$video_height=$player_height;
					$video_width=ceil($dimensions[0]/$dimensions[1]*($player_height-$inc_height));
				} elseif (intval($player_data_embed['embed_size_option'])==1)
				{
					$ratio=$dimensions[1]/$dimensions[0];
					$video_width=intval($player_data_embed['width']);
					if (intval($player_data_embed['height_option'])==1)
					{
						$video_height=intval($player_data_embed['height']);
					} else {
						$video_height=ceil($ratio*$video_width+$inc_height);
					}
				} else {
					$video_width=$dimensions[0];
					$video_height=$dimensions[1]+$inc_height;
				}
			} else {
				$slots=array();
				if ($video['is_private']==0 || $video['is_private']==1)
				{
					$slots=$player_data_embed['slots'][0];
				} elseif ($video['is_private']==2)
				{
					$slots=$player_data_embed['slots'][1];
				}
				if (count($slots)>0)
				{
					foreach ($slots as $slot)
					{
						foreach ($video_formats as $format_rec)
						{
							if ($slot['type']=='redirect' || $slot['type']<>$format_rec['postfix'])
							{
								continue;
							}
							if ($player_width>0 && $player_height>0)
							{
								$video_width=$player_width;
								$video_height=$player_height;
							} elseif ($player_width>0)
							{
								$video_width=$player_width;
								$video_height=ceil($format_rec['dimensions'][1]/$format_rec['dimensions'][0]*$player_width+$inc_height);
							} elseif ($player_height>0)
							{
								$video_height=$player_height;
								$video_width=ceil($format_rec['dimensions'][0]/$format_rec['dimensions'][1]*($player_height-$inc_height));
							} elseif (intval($player_data_embed['embed_size_option'])==1)
							{
								$ratio=$format_rec['dimensions'][1]/$format_rec['dimensions'][0];
								$video_width=intval($player_data_embed['width']);
								if (intval($player_data_embed['height_option'])==1)
								{
									$video_height=intval($player_data_embed['height']);
								} else {
									$video_height=ceil($ratio*$video_width+$inc_height);
								}
							} else {
								$video_width=$format_rec['dimensions'][0];
								$video_height=$format_rec['dimensions'][1]+$inc_height;
							}
							break 2;
						}
					}
				}
			}
			$options=array();
			if ($player_autoplay!='')
			{
				$options[]="autoplay=$player_autoplay";
			}
			if ($player_skin!='')
			{
				$options[]="skin=$player_skin";
			}
			if ($feed['affiliate_param_name']<>'' && $_REQUEST[$feed['affiliate_param_name']]<>'')
			{
				$options[]="$feed[affiliate_param_name]=".$_REQUEST[$feed['affiliate_param_name']];
			}
			if (count($options)>0)
			{
				$options='?'.implode('&amp;',$options);
			} else {
				$options='';
			}
			$data[$k]['embed']="<iframe width=\"$video_width\" height=\"$video_height\" src=\"$config[project_url]/embed/$video_id$options\" frameborder=\"0\" allowfullscreen webkitallowfullscreen mozallowfullscreen oallowfullscreen msallowfullscreen></iframe>";
		}
	}
}

include_once("$config[project_path]/admin/feeds/$feed_format.php");

$feed_config=array();
$feed_config['video_content_type_id']=$feed_options['video_content_type_id'];
if ($screenshot_format=='source')
{
	$feed_config['screenshot_sources']=1;
}
if ($feed_format=='csv')
{
	$feed_config['csv_separator']=$csv_separator;
	if (is_array($csv_columns))
	{
		$feed_config['csv_columns']=$csv_columns;
	}
}
if ($feed_options['enable_localization']==1)
{
	$feed_config['enable_localization']=1;
}

$format_func="{$feed_format}_format_feed";
echo $format_func($data,$feed_config);

die;

function get_video_tags($video_id,$cache,$locale)
{
	global $config;

	$cache_dir="$config[project_path]/admin/data/engine/feeds_info";
	$hash=md5($video_id.$locale);

	if (is_file("$cache_dir/$hash[0]$hash[1]/$video_id.dat") && time()-filectime("$cache_dir/$hash[0]$hash[1]/$video_id.dat")<$cache)
	{
		$data=unserialize(file_get_contents("$cache_dir/$hash[0]$hash[1]/$video_id.dat"));
		if (is_array($data) && is_array($data['tags']))
		{
			return $data['tags'];
		}
	}

	$tag_field="tag";
	if ($locale!='')
	{
		$tag_field="case when tag_$locale<>'' then tag_$locale else tag end";
	}
	$data['tags']=mr2array_list(sql_pr("select (select $tag_field from $config[tables_prefix]tags where tag_id=$config[tables_prefix]tags_videos.tag_id) as tag from $config[tables_prefix]tags_videos where $config[tables_prefix]tags_videos.video_id=$video_id order by id asc"));

	if ($cache>0)
	{
		if (!is_dir("$cache_dir")) {mkdir("$cache_dir",0777);chmod("$cache_dir",0777);}
		if (!is_dir("$cache_dir/$hash[0]$hash[1]")) {mkdir("$cache_dir/$hash[0]$hash[1]",0777);chmod("$cache_dir/$hash[0]$hash[1]",0777);}
		$fp=fopen("$cache_dir/$hash[0]$hash[1]/$video_id.dat","w");
		flock($fp,LOCK_EX);
		fwrite($fp,serialize($data));
		fclose($fp);
	}

	return $data['tags'];
}

function get_video_categories($video_id,$cache,$locale)
{
	global $config;

	$cache_dir="$config[project_path]/admin/data/engine/feeds_info";
	$hash=md5($video_id.$locale);

	if (is_file("$cache_dir/$hash[0]$hash[1]/$video_id.dat") && time()-filectime("$cache_dir/$hash[0]$hash[1]/$video_id.dat")<$cache)
	{
		$data=unserialize(file_get_contents("$cache_dir/$hash[0]$hash[1]/$video_id.dat"));
		if (is_array($data) && is_array($data['categories']))
		{
			return $data['categories'];
		}
	}

	$title_field="title";
	if ($locale!='')
	{
		$title_field="case when title_$locale<>'' then title_$locale else title end";
	}
	$data['categories']=mr2array_list(sql_pr("select (select $title_field from $config[tables_prefix]categories where category_id=$config[tables_prefix]categories_videos.category_id) as title from $config[tables_prefix]categories_videos where $config[tables_prefix]categories_videos.video_id=$video_id order by id asc"));

	if ($cache>0)
	{
		if (!is_dir("$cache_dir")) {mkdir("$cache_dir",0777);chmod("$cache_dir",0777);}
		if (!is_dir("$cache_dir/$hash[0]$hash[1]")) {mkdir("$cache_dir/$hash[0]$hash[1]",0777);chmod("$cache_dir/$hash[0]$hash[1]",0777);}
		$fp=fopen("$cache_dir/$hash[0]$hash[1]/$video_id.dat","w");
		flock($fp,LOCK_EX);
		fwrite($fp,serialize($data));
		fclose($fp);
	}

	return $data['categories'];
}

function get_video_models($video_id,$cache,$locale)
{
	global $config;

	$cache_dir="$config[project_path]/admin/data/engine/feeds_info";
	$hash=md5($video_id.$locale);

	if (is_file("$cache_dir/$hash[0]$hash[1]/$video_id.dat") && time()-filectime("$cache_dir/$hash[0]$hash[1]/$video_id.dat")<$cache)
	{
		$data=unserialize(file_get_contents("$cache_dir/$hash[0]$hash[1]/$video_id.dat"));
		if (is_array($data) && is_array($data['models']))
		{
			return $data['models'];
		}
	}

	$title_field="title";
	if ($locale!='')
	{
		$title_field="case when title_$locale<>'' then title_$locale else title end";
	}
	$data['models']=mr2array_list(sql_pr("select (select $title_field from $config[tables_prefix]models where model_id=$config[tables_prefix]models_videos.model_id) as title from $config[tables_prefix]models_videos where $config[tables_prefix]models_videos.video_id=$video_id order by id asc"));

	if ($cache>0)
	{
		if (!is_dir("$cache_dir")) {mkdir("$cache_dir",0777);chmod("$cache_dir",0777);}
		if (!is_dir("$cache_dir/$hash[0]$hash[1]")) {mkdir("$cache_dir/$hash[0]$hash[1]",0777);chmod("$cache_dir/$hash[0]$hash[1]",0777);}
		$fp=fopen("$cache_dir/$hash[0]$hash[1]/$video_id.dat","w");
		flock($fp,LOCK_EX);
		fwrite($fp,serialize($data));
		fclose($fp);
	}

	return $data['models'];
}

function print_doc($feed)
{
	global $config,$screenshot_formats,$video_formats_standard,$video_formats_premium,$languages;

	$feed_options=@unserialize($feed['options']);
	$screenshot_formats_str=implode(', ',$screenshot_formats);
	$video_formats_standard_str=implode(', ',$video_formats_standard);
	$video_formats_premium_str=implode(', ',$video_formats_premium);

	echo "This page describes feed usage. Options must be passed using HTTP GET parameters, e.g.:\n";
	echo "\n";
	echo "$config[project_url]/admin/feeds/$feed[external_id]/?option1=value1&option2=value2&option3=value3\n";
	echo "\n";
	echo "================================================================================\n";
	echo "DELETED VIDEOS\n";
	echo "================================================================================\n";
	echo "\n";
	echo "- action=get_deleted_ids [optional]:\n";
	echo "\n";
	echo "    Displays IDs list of deleted videos. Use [days=N] parameter to query only videos\n";
	echo "    deleted during last N days.\n";
	echo "\n";
	echo "- action=get_deleted_urls [optional]:\n";
	echo "\n";
	echo "    Displays URLs list of deleted videos. Use [days=N] parameter to query only videos\n";
	echo "    deleted during last N days.\n";
	echo "\n";
	echo "================================================================================\n";
	echo "OPTIONS\n";
	echo "================================================================================\n";
	echo "\n";
	if ($feed['password']<>'')
	{
		echo "- password [string, required]:\n";
		echo "\n";
		echo "    This feed is protected, you must pass the password as you given by feed\n";
		echo "    owner.\n";
		echo "\n";
	}
	echo "- feed_format [enumeration(csv, kvs), required]:\n";
	echo "\n";
	echo "    Select one of the valid feed formats. For \"csv\" format you must also specify\n";
	echo "    \"csv_columns\" option and additionally you can specify which column separator\n";
	echo "    should be used in \"csv_separator\" option.\n";
	echo "\n";
	if ($feed_options['enable_localization']==1)
	{
		$languages_str='';
		foreach ($languages as $language)
		{
			$languages_str.=", $language[code]";
		}
		$languages_str=trim($languages_str, ", ");
		echo "- locale [enumeration($languages_str), optional]:\n";
		echo "\n";
		echo "    Select one of the available feed locales. If specified, feed will return only\n";
		echo "    videos that have been translated to this locale with their translated values.\n";
		echo "\n";
	}
	if ($feed['affiliate_param_name']<>'')
	{
		echo "- $feed[affiliate_param_name] [string, optional]:\n";
		echo "\n";
		echo "    You can use this parameter to specify your affiliate identifier, so that all\n";
		echo "    links returned by the feed are created with your value.\n";
		echo "\n";
	}
	echo "- limit [integer, optional]:\n";
	echo "\n";
	echo "    Specify the maximum number of videos returned by feed. The maximum allowed\n";
	echo "    number of videos returned by feed per one request is $feed[max_limit].\n";
	echo "\n";
	echo "- start [integer, optional]:\n";
	echo "\n";
	echo "    Specify the minimum video ID, which will be returned by feed.\n";
	echo "\n";
	echo "- days [integer, optional]:\n";
	echo "\n";
	echo "    Specify the number of passed days you want to get videos for (e.g.\n";
	echo "    specifying 5 days will return videos posted during the last 5 days including\n";
	echo "    today).\n";
	echo "\n";
	echo "- min_duration [integer, optional]:\n";
	echo "\n";
	echo "    Specify minimum duration in seconds to get videos with greater duration.\n";
	echo "\n";
	echo "- max_duration [integer, optional]:\n";
	echo "\n";
	echo "    Specify maximum duration in seconds to get videos with less duration.\n";
	echo "\n";
	echo "- sponsor [string, optional]:\n";
	echo "\n";
	echo "    Specify sponsor (content source) to restrict only videos from this sponsor\n";
	echo "    in feed result.\n";
	echo "\n";
	echo "- category [string, optional]:\n";
	echo "\n";
	echo "    Specify category to restrict only videos from this category in feed result.\n";
	echo "\n";
	echo "- tag [string, optional]:\n";
	echo "\n";
	echo "    Specify tag to restrict only videos with this tag in feed result.\n";
	echo "\n";
	echo "- model [string, optional]:\n";
	echo "\n";
	echo "    Specify model to restrict only videos with this model in feed result.\n";
	echo "\n";
	if ($config['dvds_mode']=='channels')
	{
		echo "- channel [string, optional]:\n";
		echo "\n";
		echo "    Specify channel to restrict only videos from this channel in feed result.\n";
		echo "\n";
	}
	if (count($video_formats_standard)>1)
	{
		echo "- video_format_standard [enumeration($video_formats_standard_str), optional]:\n";
		echo "\n";
		echo "    Select one of the valid video formats for standard videos. If this option\n";
		echo "    is omitted, video files of the first available format will be used by\n";
		echo "    default.\n";
		echo "\n";
	}
	if (count($video_formats_premium)>1)
	{
		echo "- video_format_premium [enumeration($video_formats_premium_str), optional]:\n";
		echo "\n";
		echo "    Select one of the valid video formats for premium videos. If this option\n";
		echo "    is omitted, video files of the first available format will be used by\n";
		echo "    default.\n";
		echo "\n";
	}
	echo "- screenshot_format [enumeration($screenshot_formats_str), optional]:\n";
	echo "\n";
	echo "    Select one of the valid screenshot formats. If this option is omitted,\n";
	echo "    screenshots of the first available format will be returned by feed.\n";
	echo "\n";
	echo "- sorting [enumeration(video_id, rating, popularity, duration, post_date) + enumeration(asc, desc), optional]:\n";
	echo "\n";
	echo "    Select one of the valid sorting methods. If this option is omitted,\n";
	echo "    \"post_date desc\" method will be used by default (returning recently added\n";
	echo "    videos first).\n";
	echo "\n";
	if (in_array($feed_options['video_content_type_id'],array(2,3,4)))
	{
		echo "- player_skin [enumeration(black, white), optional]:\n";
		echo "\n";
		echo "    Specify which skin you would like to use in embed player.\n";
		echo "\n";
		echo "- player_autoplay [enumeration(true, false), optional]:\n";
		echo "\n";
		echo "    Specify whether you want player to start video immediately.\n";
		echo "\n";
		echo "- player_width [integer, optional]:\n";
		echo "\n";
		echo "    Specify embed player width, which will suit your website. If this option is\n";
		echo "    omitted, player for every video will have the same width, as video.\n";
		echo "\n";
		echo "- player_height [integer, optional]:\n";
		echo "\n";
		echo "    Specify embed player height, which will suit your website. If this option is\n";
		echo "    omitted, player for every video will have the same height, as video.\n";
		echo "\n";
	}
	echo "- csv_separator [char, optional]:\n";
	echo "\n";
	echo "    Can be used for csv format only. Specify which separator will be used to\n";
	echo "    separate CSV fields in result. By default | (vertical pipe) will be used.\n";
	echo "\n";
	echo "- csv_columns [list, optional]:\n";
	echo "\n";
	echo "    Can be used for csv format only. Specify the required CSV fields separated\n";
	echo "    by | (vertical pipe) in the required order. The following fields are\n";
	echo "    supported:\n";
	echo "\n";
	echo "    - id                    [integer]: video unique internal identifier\n";
	echo "    - title                  [string]: video title\n";
	if ($feed_options['enable_localization']==1)
	{
		foreach ($languages as $language)
		{
			echo "    - title_$language[code]               [string]: video title ($language[title]), can be empty if not translated\n";
		}
	}
	echo "    - description            [string]: video description\n";
	if ($feed_options['enable_localization']==1)
	{
		foreach ($languages as $language)
		{
			echo "    - description_$language[code]         [string]: video description ($language[title]), can be empty if not translated\n";
		}
	}
	echo "    - dir                    [string]: video directory (used for building se-friendly URLs)\n";
	if ($feed_options['enable_localization']==1)
	{
		foreach ($languages as $language)
		{
			echo "    - dir_$language[code]                 [string]: video directory (used for building se-friendly URLs) ($language[title]), can be empty if not translated\n";
		}
	}
	echo "    - rating                  [float]: video rating\n";
	echo "    - popularity                [int]: video page views\n";
	echo "    - post_date            [datetime]: date and time this video was added\n";
	echo "    - user                   [string]: username of person who added video\n";
	echo "    - content_source         [string]: video content source\n";
	echo "    - content_source_url     [string]: video content source url\n";
	echo "    - dvd                    [string]: dvd this video belongs to\n";
	echo "    - link                   [string]: url to page with this video\n";
	echo "    - categories               [list]: comma-separated list of video categories\n";
	echo "    - tags                     [list]: comma-separated list of video tags\n";
	echo "    - models                   [list]: comma-separated list of video models\n";
	echo "    - release_year          [integer]: video release year\n";
	echo "    - duration              [integer]: video duration in seconds\n";
	if ($feed_options['video_content_type_id']==2)
	{
		echo "    - width                 [integer]: video width in pixels\n";
		echo "    - height                [integer]: video height in pixels\n";
		echo "    - filesize              [integer]: video filesize in bytes\n";
		echo "    - url                    [string]: url to video file\n";
		echo "    - embed                  [string]: embed code with this video\n";
	} elseif ($feed_options['video_content_type_id']==3)
	{
		echo "    - embed                  [string]: embed code with this video\n";
	} elseif ($feed_options['video_content_type_id']==4)
	{
		echo "    - width                 [integer]: video width in pixels\n";
		echo "    - height                [integer]: video height in pixels\n";
		echo "    - filesize              [integer]: video filesize in bytes\n";
		echo "    - url                    [string]: temporary url to video file\n";
		echo "    - embed                  [string]: embed code with this video\n";
	}
	echo "    - screenshots_prefix     [string]: url prefix for all screenshot urls\n";
	echo "    - main_screenshot        [string]: url to video main screenshot (part of url if screenshots_prefix is specified)\n";
	echo "    - main_screenshot_number [string]: video main screenshot number\n";
	echo "    - screenshots              [list]: comma-separated list of video screenshot urls (parts of url if screenshots_prefix is specified)\n";
	if ($feed_options['enable_custom_fields']==1)
	{
		echo "    - custom1                [string]: custom 1 field\n";
		echo "    - custom2                [string]: custom 2 field\n";
		echo "    - custom3                [string]: custom 3 field\n";
	}
	echo "\n";
	echo "    If this option is omitted, the following fields will be displayed in result\n";
	echo "    in the following order:\n";
	echo "\n";
	echo "    id|title|description|post_date|content_source|link|categories|tags|duration";
	if ($feed_options['video_content_type_id']==2)
	{
		echo "|url";
	} elseif ($feed_options['video_content_type_id']==3)
	{
		echo "|embed";
	} elseif ($feed_options['video_content_type_id']==4)
	{
		echo "|url";
	}
	echo "|main_screenshot\n";
	echo "\n";
	echo "================================================================================\n";
	echo "EXAMPLES\n";
	echo "================================================================================\n";
	echo "\n";
	echo "- Get all videos added today in KVS format:\n";
	echo "\n";
	echo "    $config[project_url]/admin/feeds/$feed[external_id]/?feed_format=kvs&days=1\n";
	echo "\n";
	echo "- Get 20 most popular videos posted during last week in CSV format:\n";
	echo "\n";
	echo "    $config[project_url]/admin/feeds/$feed[external_id]/?feed_format=csv&days=7&limit=20&sorting=rating\n";
	echo "\n";
	echo "- Get 10 latest videos in CSV format of specific structure:\n";
	echo "\n";
	echo "    $config[project_url]/admin/feeds/$feed[external_id]/?feed_format=csv&limit=10&csv_columns=id|title|description|duration|link|tags|screenshots_prefix|screenshots\n";
	echo "\n";
	echo "================================================================================\n\n";
	echo "Powered by Kernel Video Sharing, professional video management, community and tube software.";
}

?>