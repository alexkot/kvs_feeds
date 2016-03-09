<?php
/* Developed by Kernel Team.
   http://kernel-team.com
*/

function csv_parse_feed($url,$feed_config)
{
	global $config;

	$feed_contents=get_page('',$url,'','',1,0,600,'');
	if (strlen($feed_contents)==0)
	{
		return null;
	}
	$rows=explode("\n",$feed_contents);
	if ($feed_config['csv_skip_first_row']==1)
	{
		unset($rows[0]);
	}

	$result=array();
	foreach($rows as $row)
	{
		if (strlen(trim($row))==0)
		{
			continue;
		}
		$video_record=array();
		if (function_exists('str_getcsv') && strlen($feed_config['separator'])==1)
		{
			$columns=str_getcsv($row,$feed_config['separator']);
		} else {
			$columns=explode($feed_config['separator'],$row);
		}
		for ($i=0;$i<count($feed_config['fields']);$i++)
		{
			$field=$feed_config['fields'][$i];
			$value=trim($columns[$i]);
			if (strlen($value)>1 && $value[0]=='"' && $value[strlen($value)-1]=='"')
			{
				$value=str_replace('""','"',substr($value,1,strlen($value)-2));
			}
			if (strpos($field,'pass')===0)
			{
				continue;
			}
			if ($field=='video_file')
			{
				$video_file=array();
				$video_file['url']=$value;
				$video_record['video_files']=array();
				$video_record['video_files'][]=$video_file;
			} elseif ($field=='screenshot_main_source')
			{
				$video_record['screenshots']=array();
				$video_record['screenshots'][]=$value;
			} elseif ($field=='overview_screenshots_sources')
			{
				$video_record['screenshots']=array_map('trim',explode(',',$value));
			}
			$video_record[$field]=$value;
		}
		if ($video_record['screenshots_prefix']<>'')
		{
			if (is_array($video_record['screenshots']))
			{
				foreach ($video_record['screenshots'] as $k=>$v)
				{
					$video_record['screenshots'][$k]=$video_record['screenshots_prefix'].$v;
				}
			}
		}
		if ($video_record['duration']<>'' && is_array($video_record['video_files']))
		{
			$video_record['video_files'][0]['duration']=$video_record['duration'];
		}
		if (is_array($video_record['screenshots']) && is_array($video_record['video_files']))
		{
			$video_record['video_files'][0]['screenshots']=$video_record['screenshots'];
		}
		$video_record['external_key']=$video_record[$feed_config['key_field']];
		$result[]=$video_record;
	}

	return $result;
}

function csv_check_feed_content($url,$feed_config)
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
	$rows=explode("\n",$feed_contents);
	$first_row=trim($rows[0]);
	if ($feed_config['csv_skip_first_row']==1)
	{
		if (count($rows)>1)
		{
			$first_row=trim($rows[1]);
		} else {
			return null;
		}
	}

	if (function_exists('str_getcsv') && strlen($feed_config['separator'])==1)
	{
		$columns=array_map('trim',str_getcsv($first_row,$feed_config['separator']));
	} else {
		$columns=array_map('trim',explode($feed_config['separator'],$first_row));
	}
	return $columns;
}

function csv_format_feed($videos,$feed_config)
{
	global $config,$languages;

	header("Content-Type: text/plain; charset=utf-8");

	$csv_separator=$feed_config['csv_separator'];
	if ($csv_separator=='')
	{
		$csv_separator='|';
	}
	$csv_columns=$feed_config['csv_columns'];
	if (!is_array($csv_columns))
	{
		$csv_columns=array();
		$csv_columns[]='id';
		$csv_columns[]='title';
		$csv_columns[]='dir';
		$csv_columns[]='description';
		$csv_columns[]='post_date';
		$csv_columns[]='content_source';
		$csv_columns[]='content_source_url';
		$csv_columns[]='link';
		$csv_columns[]='categories';
		$csv_columns[]='tags';
		$csv_columns[]='duration';
		if ($feed_config['video_content_type_id']==2)
		{
			$csv_columns[]='url';
			$csv_columns[]='embed';
		} elseif ($feed_config['video_content_type_id']==3)
		{
			$csv_columns[]='embed';
		} elseif ($feed_config['video_content_type_id']==4)
		{
			$csv_columns[]='url';
			$csv_columns[]='embed';
		}
		$csv_columns[]='main_screenshot';
	}
	$result='';

	foreach ($videos as $video)
	{
		$dir_path=get_dir_by_id($video['video_id']);

		$row='';
		foreach ($csv_columns as $field)
		{
			if ($row<>'')
			{
				$row.=$csv_separator;
			}
			switch ($field)
			{
				case 'id':
					$row.=$video['video_id'];
				break;
				case 'title':
					$row.=$video['title'];
				break;
				case 'dir':
					$row.=$video['dir'];
				break;
				case 'description':
					$row.=$video['description'];
				break;
				case 'rating':
					$row.=round($video['rating']*10)/10;
				break;
				case 'popularity':
					$row.=$video['popularity'];
				break;
				case 'post_date':
					$row.=$video['post_date'];
				break;
				case 'user':
					$row.=$video['user_title'];
				break;
				case 'content_source':
					$row.=$video['cs_title'];
				break;
				case 'content_source_url':
					$row.=$video['cs_url'];
				break;
				case 'dvd':
					$row.=$video['dvd_title'];
				break;
				case 'link':
					$row.=$video['website_link'];
				break;
				case 'categories':
					$row.=implode(',',$video['categories']);
				break;
				case 'tags':
					$row.=implode(',',$video['tags']);
				break;
				case 'models':
					$row.=implode(',',$video['models']);
				break;
				case 'release_year':
					if ($video['release_year']>0)
					{
						$row.=$video['release_year'];
					}
					break;
				case 'duration':
					if (is_array($video['hotlink_format']))
					{
						$row.=$video['hotlink_format']['duration'];
					} else {
						$row.=$video['duration'];
					}
				break;
				case 'width':
					if (is_array($video['hotlink_format']))
					{
						$row.=$video['hotlink_format']['dimensions'][0];
					} else {
						$dimensions=explode("x",$video['file_dimensions']);
						$row.=$dimensions[0];
					}
				break;
				case 'height':
					if (is_array($video['hotlink_format']))
					{
						$row.=$video['hotlink_format']['dimensions'][1];
					} else {
						$dimensions=explode("x",$video['file_dimensions']);
						$row.=$dimensions[1];
					}
				break;
				case 'filesize':
					if (is_array($video['hotlink_format']))
					{
						$row.=$video['hotlink_format']['file_size'];
					}
				break;
				case 'url':
					if (is_array($video['hotlink_format']))
					{
						if ($feed_config['video_content_type_id']==4)
						{
							$video_url="$config[project_url]/get_file/$video[server_group_id]".'/'.$video['hotlink_format']['file_path'];
							if ($config['omit_slash_video_files']<>'true')
							{
								$video_url.="/";
							}
							$time=date("YmdHis");
							$ahv=md5($video_url.$time.$config['ahv']);
							$row.="$video_url?time=$time&ahv=$ahv";
						} else {
							$row.="$config[project_url]/get_file/$video[server_group_id]".'/'.$video['hotlink_format']['file_path'];
							if ($config['omit_slash_video_files']<>'true')
							{
								$row.="/";
							}
						}
					} elseif ($video['file_url']<>'') {
						$row.=$video['file_url'];
					}
				break;
				case 'embed':
					$row.=$video['embed'];
				break;
				case 'screenshots_prefix':
					if ($feed_config['screenshot_sources']==1)
					{
						//$row.="$config[screen_project_url]/get_file/0/";
            $row.="http://txxx.com/get_file/0/";
					} else {
						$row.=$config['content_url_videos_screenshots'].'/'.$video['screen_url'].'/';
					}
				break;
				case 'main_screenshot':
					$hash=md5($config['cv']."$dir_path/$video[video_id]/screenshots/$video[screen_main].jpg");
					if (in_array('screenshots_prefix',$csv_columns))
					{
						if ($feed_config['screenshot_sources']==1)
						{
							$row.="$hash/$dir_path/$video[video_id]/screenshots/$video[screen_main].jpg";
							if ($config['omit_slash_screenshot_sources']<>'true')
							{
								$row.="/";
							}
						} else {
							$row.="$video[screen_main].jpg";
						}
					} else {
						if ($feed_config['screenshot_sources']==1)
						{
							//$row.="$config[screen_project_url]/get_file/0/$hash/$dir_path/$video[video_id]/screenshots/$video[screen_main].jpg";
              $row.="http://txxx.com/get_file/0/$hash/$dir_path/$video[video_id]/screenshots/$video[screen_main].jpg";
							if ($config['omit_slash_screenshot_sources']<>'true')
							{
								$row.="/";
							}
						} else {
							$row.="$config[content_url_videos_screenshots]/$video[screen_url]/$video[screen_main].jpg";
						}
					}
				break;
				case 'main_screenshot_number':
					$row.=$video['screen_main'];
				break;
				case 'screenshots':
					for ($i=1;$i<=$video['screen_amount'];$i++)
					{
						$hash=md5($config['cv']."$dir_path/$video[video_id]/screenshots/$i.jpg");
						if (in_array('screenshots_prefix',$csv_columns))
						{
							if ($feed_config['screenshot_sources']==1)
							{
								$row.="$hash/$dir_path/$video[video_id]/screenshots/$i.jpg";
								if ($config['omit_slash_screenshot_sources']<>'true')
								{
									$row.="/";
								}
							} else {
								$row.="$i.jpg";
							}
						} else {
							if ($feed_config['screenshot_sources']==1)
							{
								//$row.="$config[screen_project_url]/get_file/0/$hash/$dir_path/$video[video_id]/screenshots/$i.jpg";
                  $row.="http://txxx.com/get_file/0/$hash/$dir_path/$video[video_id]/screenshots/$i.jpg";
								if ($config['omit_slash_screenshot_sources']<>'true')
								{
									$row.="/";
								}
							} else {
								$row.="$config[content_url_videos_screenshots]/$video[screen_url]/$i.jpg";
							}
						}
						if ($i<$video['screen_amount'])
						{
							$row.=',';
						}
					}
				break;
				case 'custom1':
					$row.=$video['custom1'];
				break;
				case 'custom2':
					$row.=$video['custom2'];
				break;
				case 'custom3':
					$row.=$video['custom3'];
				break;
			}
			foreach ($languages as $language)
			{
				if ($field=="title_$language[code]")
				{
					$row.=$video["title_$language[code]"];
				}
				if ($field=="description_$language[code]")
				{
					$row.=$video["description_$language[code]"];
				}
				if ($field=="dir_$language[code]")
				{
					$row.=$video["dir_$language[code]"];
				}
			}
			if (strpos($field,'static:')===0)
			{
				$row.=str_replace('static:','',$field);
			}
		}
		$result.="$row\n";
	}

	return $result;
}
?>