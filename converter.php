<?php
class Converter
{
	public $tracker_in = "Bug Tracker v1.3 Final";
	public $tracker_out = "IP.Tracker (2.0.4 or 2.1.0b3)";

	public function table_convert()
	{
		$str = "
<div class='indented'><table id='convert'>
<tr><th><em>".$this->tracker_in."</em></th><td>&rarr;</td><th><em>".$this->tracker_out."</em></th></tr>
<tr><td>Category</td><td>&rarr;</td><td>Status</td></tr>
<tr><td>Severity</td><td>&rarr;</td><td>Severity (1,2,3,4,5)</td></tr>
<tr><td>Custom Fields</td><td>&rarr;</td><td>Custom Fields</td></tr>
<tr><td>Bug</td><td>&rarr;</td><td>Issue + Post</td></tr>
<tr><td>Reply</td><td>&rarr;</td><td>Post</td></tr>
</table></div>
";
		return $str;
	}

	public $url_in = "http://invisionmodding.com/index.php?autocom=downloads&showfile=520";
	public $url_out = "http://community.invisionpower.com/forum/411-iptracker/";

	public $stage = 0;
	public $vars_in = array();
	public $vars_out = array();

	public $projects = array();
	public $categories = array();
	public $severities = array();
	public $custom_fields = array();
	public $options = array();

	public function __construct()
	{
		$this->parse_variables($_POST);
		// stage
		if (isset($_POST['stage'])) $this->stage = $_POST['stage'];
	}

	public function run()
	{
		switch($this->stage)
		{
			case 18: // convert
				$this->convert_bugs();
				break;
			case 17: // convert
				$this->confirm_convert();
				break;
			case 16: // options
				$this->ask_options();
				break;
			case 15: // authors
				$this->confirm_authors();
				break;
			case 14: // structure
				$this->confirm_custom_field_transfers();
				break;
			case 13: // structure
				$this->select_custom_field_transfers();
				break;
			case 12: // structure
				$this->confirm_severity_transfers();
				break;
			case 11: // structure
				$this->select_severity_transfers();
				break;
			case 10: // structure
				$this->confirm_category_transfers();
				break;
			case 9: // structure
				$this->select_category_transfers();
				break;
			case 8: // structure
				$this->confirm_project_transfers();
				break;
			case 7: // out/structure
				if ($_POST['saction'] == "Enter Manual Settings") { $this->ask_manual_settings_out(); }
				else if ($_POST['saction'] == "Continue") { $this->select_project_transfers(); }
				else if ($_POST['dbHost_out']) { $this->confirm_settings_out(); }
				else { $this->output("Oops, something went wrong!"); }
				break;
			case 6: // out
				if ($_POST['targetboard'] != "OTHER") { $this->confirm_settings_out(); }
				else if ($_POST['targetboard'] == "OTHER") { $this->ask_manual_settings_out(); }
				else { $this->scan_local_out(); }
				break;
			case 5: // out
				if ($_POST['location'] == "scan") { $this->scan_local_out(); }
				else if ($_POST['location'] == "manual") { $this->ask_manual_settings_out(); }
				else { $this->ask_location_ipb_out(); }
				break;
			case 4: // in/out
				if ($_POST['saction'] == "Enter Manual Settings") { $this->ask_manual_settings_in(); }
				else if ($_POST['saction'] == "Continue") { $this->ask_location_ipb_out(); }
				else if ($_POST['dbHost_in']) { $this->confirm_settings_in(); }
				else { $this->output("Oops, something went wrong!"); }
				break;
			case 3: // in
				if ($_POST['targetboard'] != "OTHER") { $this->confirm_settings_in(); }
				else if ($_POST['targetboard'] == "OTHER") { $this->ask_manual_settings_in(); }
				else { $this->scan_local_in(); }
				break;
			case 2: // in
				if ($_POST['location'] == "scan") { $this->scan_local_in(); }
				else if ($_POST['location'] == "manual") { $this->ask_manual_settings_in(); }
				else { $this->ask_location_ipb_in(); }
				break;
			case 1: // in
				$this->ask_location_ipb_in();
				break;
			default:
			case 0: // welcome
				$this->display_welcome_message();
				break;
		}
	}

	//----------------------------------------------------------------------
	// private stage functions
	//----------------------------------------------------------------------

	/// (18->19) Convert bugs.
	private function convert_bugs()
	{
		$this->parse_configs();
		$str = "
<center><em>".$this->tracker_in."</em> &rarr; <em>".$this->tracker_out."</em></center>
<center><strong>Converting...</strong></center><br />".$this->table_convert();

		$str .= "<br />Options:<br /><div class='indented'>";
		foreach ($this->options as $key => $value)
			$str .= $key."=".$value."<br />";
		$str .= "</div>";

		$issues = array();// bug_id => issue
		$bugposts = array();// bug_id => post (each bug produces a new post)
		$posts = array();// reply_id => post

		$members_in = $this->sql_id2name('members','id','name',$this->vars_in);
		$members_out = $this->sql_id2name('members','member_id','name',$this->vars_out);
		$name2id_out = array();
		foreach ($members_out as $id => $name)
			$name2id_out[$name] = $id;
		$members_id2id = array();

		$str .= "<br />Processing...<br /><div class='indented'>";
		$bugs = $this->sql_id2row('bugtracker_bugs','bug_id',$this->vars_in);
		$fields_content = $this->sql_id2row('bugtracker_fields_content','bug_id',$this->vars_in);
		foreach ($bugs as $bug_id => $bug)
		{
			if (!$this->projects[$bug['project_id']])
			{
				$str .= "Skipping bug bug_id=".$bug_id." project_id=".$bug['project_id'].".<br />";
				continue;
			}
			$author_id = $bug['bug_author_id'];
			$author_name = $bug['bug_author_name'];
			if ($author_id == 0)
				;// unknown author
			elseif (!isset($members_id2id[$author_id]))
			{
				$id_in = $author_id;
				$id_out = 0;
				if (isset($members_in[$id_in]))
					$author_name = $members_in[$id_in];
				else
					$id_in = 0;
				if (isset($name2id_out[$author_name]))
					$id_out = $name2id_out[$author_name];
				else
					$id_out = 0;
				if ($id_in != 0 and $id_out != 0)
					$members_id2id[$id_in] = $id_out;
				$author_id = $id_out;
			}
			$last_reply_id = $bug['bug_last_reply_mid'];
			$last_reply_name = $bug['bug_last_reply_name'];
			if ($last_reply_id == 0)
				;// unknown author
			elseif (!isset($members_id2id[$last_reply_id]))
			{
				$id_in = $last_reply_id;
				$id_out = 0;
				if (isset($members_in[$id_in]))
					$last_reply_name = $members_in[$id_in];
				else
					$id_in = 0;
				if (isset($name2id_out[$last_reply_name]))
					$id_out = $name2id_out[$last_reply_name];
				else
					$id_out = 0;
				if ($id_in != 0 and $id_out != 0)
					$members_id2id[$id_in] = $id_out;
				$last_reply_id = $id_out;
			}
			$issue = array(
				'issue_id' => $bug_id,
				'project_id' => $this->projects[$bug['project_id']],
				'title' => $bug['bug_name'],
				'title_seo' => IPSText::makeSeoTitle($bug['bug_name']),
				'state' => $bug['bug_locked'] == 0 ? 'open' : 'closed',
				//posts - XXX generated at the end = count(posts,post.issue_id=issue.issue_id)-1
				'starter_id' => $author_id,
				'starter_name' => $author_name,
				'starter_name_seo' => IPSText::makeSeoTitle($author_name),
				'start_date' => $bug['bug_date'],
				'last_poster_id' => $last_reply_id,
				'last_poster_name' => $last_reply_name,
				'last_poster_name_seo' => IPSText::makeSeoTitle($last_reply_name),
				'last_post' => $bug['bug_last_reply_date'],
				'author_mode' => $author_id == 0 ? 0 : 1,
				'hasattach' => $bug['bug_has_attach'],
				//firstpost - XXX set when bugpost is inserted = post.pid
				//private
				//module_severity_id - DONE
				//module_status_id - DONE
				//module_versions_reported_id
				//module_versions_fixed_id
				//module_privacy
				);
			if ($this->severities[$bug['severity_id']])
				$issue['module_severity_id'] = $this->severities[$bug['severity_id']];
			if ($this->categories[$bug['category_id']])
				$issue['module_status_id'] = $this->categories[$bug['category_id']];
			$custom_fields_content = isset($fields_content[$bug_id]) ? $fields_content[$bug_id] : array();
			foreach ($this->custom_fields as $old_id => $new_id)
				if ($new_id && isset($custom_fields_content['field_'.$old_id]))
					$issue['field_'.$new_id] = $custom_fields_content['field_'.$old_id];
			$post = array(
				//pid
				'append_edit' => $bug['bug_edit_time'] != NULL and $bug['bug_edit_name'] != NULL ? 1 : 0,
				'edit_time' => $bug['bug_edit_time'],
				'author_id' => $author_id,
				'author_name' => $author_name,
				'use_sig' => $bug['bug_use_sig'],
				'use_emo' => $bug['bug_use_emo'],
				'ip_address' => $bug['ip_address'],
				'post_date' => $bug['bug_date'],
				//icon_id
				'post' => $bug['bug'],
				//queued
				'issue_id' => $bug_id,
				//post_title
				'new_issue' => 1,
				'edit_name' => $bug['bug_edit_name'],
				'post_key' => $bug['bug_post_key'],
				//post_parent
				//post_htmlstate
				//post_edit_reaon
				);
			$issues[$bug_id] = $issue;
			$bugposts[$bug_id] = $post;
		}
		$str .= count($issues)." issue(s) generated<br />";
		$str .= count($bugposts)." bugpost(s) generated<br />";
		$replies = $this->sql_id2row('bugtracker_replies','reply_id',$this->vars_in);
		foreach ($replies as $reply_id => $reply)
		{
			if (!isset($issues[$reply['bug_id']]))
			{
				$str .= "Skipping reply reply_id=".$reply_id." bug_id=".$reply['bug_id'].".<br />";
				continue;
			}
			$author_id = $reply['member_id'];
			$author_name = $reply['member_name'];
			if ($author_id == 0)
				;// unknown author
			elseif (!isset($members_id2id[$author_id]))
			{
				$id_in = $author_id;
				$id_out = 0;
				if (isset($members_in[$id_in]))
					$author_name = $members_in[$id_in];
				else
					$id_in = 0;
				if (isset($name2id_out[$author_name]))
					$id_out = $name2id_out[$author_name];
				else
					$id_out = 0;
				if ($id_in != 0 and $id_out != 0)
					$members_id2id[$id_in] = $id_out;
				$author_id = $id_out;
			}
			$post = array(
				'pid' => $reply_id,
				'append_edit' => $reply['reply_append_edit'],
				'edit_time' => $reply['reply_edit_time'],
				'author_id' => $author_id,
				'author_name' => $author_name,
				'use_sig' => $reply['reply_use_sig'],
				'use_emo' => $reply['reply_use_emo'],
				'ip_address' => $reply['ip_address'],
				'post_date' => $reply['reply_date'],
				//icon_id
				'post' => $reply['reply_text'],
				//queued
				'issue_id' => $reply['bug_id'],
				//post_title
				'new_issue' => 0,
				'edit_name' => $reply['reply_edit_name'],
				'post_key' => $reply['reply_post_key'],
				//post_parent
				//post_htmlstate
				//post_edit_reaon
				);
			$posts[$reply_id] = $post;
			$issue = $issues[$reply['bug_id']];
		}
		$str .= count($issues)." post(s) generated<br />";
		$str .= "</div>";

		$str .= "<br />Modifying database...<br /><div class='indented'>";
		if ($this->options['issues'] == 'delete')
		{
			$countIssues = $this->sql_count('tracker_issues',$this->vars_out);
			$this->sql_truncate('tracker_issues',$this->vars_out);
			$str .= $countIssues." issue(s) deleted<br />";
		}
		if ($this->options['posts'] == 'delete' or ($this->options['issues'] == 'delete' and $this->options['delete_posts_with_issue'] == 'on'))
		{
			$countPosts = $this->sql_count('tracker_posts',$this->vars_out);
			$this->sql_truncate('tracker_posts',$this->vars_out);
			$str .= $countPosts." post(s) deleted<br />";
		}
		// issues
		$str .= "(issues)<br />";
		if (count($issues) > 0)
		{
			if ($this->options['issues'] == 'replace')
			{
				$whereIssueId = "WHERE issue_id IN (".implode(',',array_keys($issues)).")";
				$countIssues = $this->sql_count('tracker_issues',$this->vars_out,$whereIssueId);
				$this->sql_delete('tracker_issues',$this->vars_out,$whereIssueId);
				$str .= $countIssues." issue(s) deleted<br />";
				if ($this->options['delete_posts_with_issue'] == 'on')
				{
					$countPosts = $this->sql_count('tracker_posts',$this->vars_out,$whereIssueId);
					$this->sql_delete('tracker_posts',$this->vars_out,$whereIssueId);
					$str .= $countPosts." posts(s) deleted<br />";
				}
			}
			foreach (array_values($issues) as $row)
				$this->sql_insert('tracker_issues',$row,$this->vars_out);
			$str .= count($issues)." issue(s) inserted<br />";
		}
		// posts
		$str .= "(posts)<br />";
		if (count($posts) > 0)
		{
			if ($this->options['posts'] == 'replace')
			{
				$wherePid = "WHERE pid IN (".implode(',',array_keys($posts)).")";
				$countPosts = $this->sql_count('tracker_posts',$this->vars_out,$wherePid);
				$this->sql_delete('tracker_posts',$this->vars_out,$wherePid);
				$str .= $countPosts." posts(s) deleted<br />";
			}
			foreach (array_values($posts) as $row)
				$this->sql_insert('tracker_posts',$row,$this->vars_out);
			$str .= count($issues)." post(s) inserted<br />";
		}
		// bugposts
		$str .= "(bugposts)<br />";
		if (count($bugposts) > 0)
		{
			foreach ($bugposts as $issue_id => $row)
			{
				$issue = array();
				$issue['posts'] = $this->sql_count('tracker_posts',$this->vars_out,"WHERE issue_id=".$issue_id);
				$issue['firstpost'] = $this->sql_insert('tracker_posts',$row,$this->vars_out);
				$this->sql_update('tracker_issues',$issue,$this->vars_out,"WHERE issue_id=".$issue_id);
			}
			$str .= count($issues)." bugpost(s) inserted<br />";
		}
		$str .= "</div>";

		$str .= "<center><input type='submit' value='DONE' /></center>";
		$this->vars_in = array();
		$this->vars_out = array();
		$this->projects = array();
		$this->categories = array();
		$this->severities = array();
		$this->custom_fields = array();
		$this->options = array();
		$this->output($str);
	}

	/// (17->18) Confirm convert.
	private function confirm_convert()
	{
		$str = "
<center><em>".$this->tracker_in."</em> &rarr; <em>".$this->tracker_out."</em></center>
<center><strong>Start Converting</strong></center><br />
This is the LAST CHANCE to stop before changes are done to the database.<br /><br />
<input type='hidden' name='stage' value='18' />
<input type='submit' value='Continue' />
";
		$this->output($str);
	}

	/// (16->17) Options.
	private function ask_options()
	{
		$this->parse_configs();
		$options = array();
		$str = "
<center><em>".$this->tracker_in."</em> &rarr; <em>".$this->tracker_out."</em></center>
<center><strong>Options</strong></center><br />
";

		$str .= "Checking <em>".$this->tracker_out."</em>...<br />";

		$countIssues = $this->sql_count('tracker_issues',$this->vars_out);
		$str .= $countIssues." issue(s) detected<br />";
		if ($countIssues > 0)
		{
			$str .= "
<div class='indented'>
<input type='radio' name='option_issues' value='delete'>Delete all</input><br />
<input type='radio' name='option_issues' value='replace' checked>Replace same id</input><br />
</div>";
			array_push($options,'issues');
			array_push($options,'delete_posts_with_issue');
		}

		$countPosts = $this->sql_count('tracker_posts',$this->vars_out);
		$str .= $countPosts." post(s) detected<br />";
		if ($countPosts > 0)
		{
			$str .= "
<div class='indented'>
<input type='radio' name='option_posts' value='delete'>Delete all</input><br />
<input type='radio' name='option_posts' value='replace' checked>Replace same id</input><br />
<input type='checkbox' name='option_delete_posts_with_issue' value='on' checked>Delete posts with issue</input><br />
</div>";
			array_push($options,'posts');
		}

		if (count($options) > 0)
			$str .= "<input type='hidden' name='options' value='".implode(',',$options)."' />";

		$str .= "
<br />
<input type='hidden' name='stage' value='17' />
<input type='submit' value='Continue' />
";
		$this->output($str);
	}

	/// (15->16) Confirm authors.
	private function confirm_authors()
	{
		$this->parse_configs();
		$str = "
<center><em>".$this->tracker_in."</em> &rarr; <em>".$this->tracker_out."</em></center>
<center><strong>Checking Authors...</strong></center><br />
";
		$members_id2id = array();

		$str .= "Checking authors in <em>".$this->tracker_in."</em>...<br />";
		$missing = array();
		$members_in = $this->sql_id2name('members','id','name',$this->vars_in);
		$str .= count($members_in)." member(s) detected<br />";
		$authors_in = $this->sql_id2name('bugtracker_bugs','bug_author_id','bug_author_name',$this->vars_in,"WHERE NOT bug_author_id=0");
		$str .= count($authors_in)." bug author(s) detected<br />";
		foreach ($authors_in as $author_id => $author_name)
		{
			if ($author_id == 0)
				;// unknown author
			elseif (isset($members_in[$author_id]))
				$members_id2id[$author_id] = $members_in[$author_id];
			elseif(!$missing[$author_name])
			{
				$str .= "<div class='negative'>not found: id=".$author_id." name=".$author_name." </div>";
				$missing[$author_name] = true;
			}
		}
		$authors_in = $this->sql_id2name('bugtracker_bugs','bug_last_reply_mid','bug_last_reply_name',$this->vars_in,"WHERE NOT bug_last_reply_mid=0");
		$str .= count($authors_in)." last reply author(s) detected<br />";
		foreach ($authors_in as $author_id => $author_name)
		{
			if ($author_id == 0)
				;// unknown author
			elseif (isset($members_in[$author_id]))
				$members_id2id[$author_id] = $members_in[$author_id];
			elseif(!$missing[$author_name])
			{
				$str .= "<div class='negative'>not found: id=".$author_id." name=".$author_name." </div>";
				$missing[$author_name] = true;
			}
		}
		$authors_in = $this->sql_id2name('bugtracker_replies','member_id','member_name',$this->vars_in,"WHERE NOT member_id=0");
		$str .= count($authors_in)." reply author(s) detected<br />";
		foreach ($authors_in as $author_id => $author_name)
		{
			if ($author_id == 0)
				;// unknown author
			if (isset($members_in[$author_id]))
				$members_id2id[$author_id] = $members_in[$author_id];
			else if(!$missing[$author_name])
			{
				$str .= "<div class='negative'>not found: id=".$author_id." name=".$author_name." </div>";
				$missing[$author_name] = true;
			}
		}
		if (count($missing) > 0)
			$str .= "<div class='indented'><em>Missing authors will have id=0 when converting.</em></div>";

		$str .= "<br />Checking authors in <em>".$this->tracker_out."</em>...<br />";
		$missing = false;
		$members_out = $this->sql_id2name('members','member_id','name',$this->vars_out);
		$str .= count($members_out)." member(s) detected<br />";
		$name2id_out = array();
		foreach ($members_out as $member_id => $member_name)
			$name2id_out[$member_name] = $member_id;
		foreach (array_keys($members_id2id) as $author_id)
		{
			$author_name = $members_in[$author_id];
			if (isset($name2id_out[$author_name]))
				$members_id2id[$author_id] = $name2id_out[$author_name];
			else
			{
				$str .= "<div class='negative'>not found: id=".$author_id." name=".$author_name."</div>";
				$missing = true;
			}
		}
		if ($missing)
			$str .= "<div class='indented'><em>Missing authors will have id=0 when converting.</em></div>";

		$str .= "
<br />
<input type='hidden' name='stage' value='16' />
<input type='submit' value='Continue' />
";
		$this->output($str);
	}

	/// (14->15) Confirm custom field transfers.
	private function confirm_custom_field_transfers()
	{
		$settings = array(
			'title' => "Custom Field",
			'fieldList' => "custom_fields",
			'fieldPrefix' => "custom_field_",
			'tbl_in' => 'bugtracker_fields_data',
			'colId_in' => 'field_id',
			'colName_in' => 'field_title',
			'tbl_out' => 'tracker_module_custom',
			'colId_out' => 'field_id',
			'colName_out' => 'title',
			'stage' => 15,
		);
		$this->confirm_transfers($settings,$this->custom_fields);
	}

	/// (13->14) Select custom field transfers.
	private function select_custom_field_transfers()
	{
		$settings = array(
			'title' => "Custom Field",
			'fieldList' => "custom_fields",
			'fieldPrefix' => "custom_field_",
			'tbl_in' => 'bugtracker_fields_data',
			'colId_in' => 'field_id',
			'colName_in' => 'field_title',
			'tbl_out' => 'tracker_module_custom',
			'colId_out' => 'field_id',
			'colName_out' => 'title',
			'stage' => 14,
		);
		$this->select_transfers($settings);
	}

	/// (12->13) Confirm severity transfers.
	private function confirm_severity_transfers()
	{
		$settings = array(
			'title' => "Severities",
			'fieldList' => "severities",
			'fieldPrefix' => "severity_",
			'tbl_in' => 'bugtracker_severities',
			'colId_in' => 'severity_id',
			'colName_in' => 'severity_name',
			'tbl_out' => 'tracker_module_severity',
			'colId_out' => 'severity_id',
			'colName_out' => 'severity_id',
			'stage' => 13,
			'extraText' => "<em>".$this->tracker_out."</em> Severities:<div class='indented'>1 = Low<br />2 = Fair<br />3 = Medium<br />4 = High<br />5 = Critical</div><br />",
		);
		$this->confirm_transfers($settings,$this->severities);
	}

	/// (11->12) Select severity transfers.
	private function select_severity_transfers()
	{
		$settings = array(
			'title' => "Severities",
			'fieldList' => "severities",
			'fieldPrefix' => "severity_",
			'tbl_in' => 'bugtracker_severities',
			'colId_in' => 'severity_id',
			'colName_in' => 'severity_name',
			'tbl_out' => 'tracker_module_severity',
			'colId_out' => 'severity_id',
			'colName_out' => 'severity_id',
			'stage' => 12,
			'extraText' => "<em>".$this->tracker_out."</em> Severities:<div class='indented'>1 = Low<br />2 = Fair<br />3 = Medium<br />4 = High<br />5 = Critical</div><br />",
		);
		$this->select_transfers($settings);
	}

	/// (10->11) Confirm category transfers.
	private function confirm_category_transfers()
	{
		$settings = array(
			'title' => "Category/Status",
			'fieldList' => "categories",
			'fieldPrefix' => "category_",
			'tbl_in' => 'bugtracker_categories',
			'colId_in' => 'category_id',
			'colName_in' => 'category_name',
			'tbl_out' => 'tracker_module_status',
			'colId_out' => 'status_id',
			'colName_out' => 'title',
			'stage' => 11,
		);
		$this->confirm_transfers($settings,$this->categories);
	}

	/// (9->10) Select category transfers.
	private function select_category_transfers()
	{
		$settings = array(
			'title' => "Category/Status",
			'fieldList' => "categories",
			'fieldPrefix' => "category_",
			'tbl_in' => 'bugtracker_categories',
			'colId_in' => 'category_id',
			'colName_in' => 'category_name',
			'tbl_out' => 'tracker_module_status',
			'colId_out' => 'status_id',
			'colName_out' => 'title',
			'stage' => 10,
		);
		$this->select_transfers($settings);
	}

	/// (8->9) Confirm project transfers.
	private function confirm_project_transfers()
	{
		$settings = array(
			'title' => "Project",
			'fieldList' => "projects",
			'fieldPrefix' => "project_",
			'tbl_in' => 'bugtracker_projects',
			'colId_in' => 'project_id',
			'colName_in' => 'project_name',
			'tbl_out' => 'tracker_projects',
			'colId_out' => 'project_id',
			'colName_out' => 'title',
			'stage' => 9,
			'extraText' => "<em>All bugs/issues and replies/posts that belong to ignored projects are skipped.</em><br /><br />",
		);
		$this->confirm_transfers($settings,$this->projects);
	}

	/// (7->8) Select project transfers.
	private function select_project_transfers()
	{
		$settings = array(
			'title' => "Project",
			'fieldList' => "projects",
			'fieldPrefix' => "project_",
			'tbl_in' => 'bugtracker_projects',
			'colId_in' => 'project_id',
			'colName_in' => 'project_name',
			'tbl_out' => 'tracker_projects',
			'colId_out' => 'project_id',
			'colName_out' => 'title',
			'stage' => 8,
			'extraText' => "<em>All bugs/issues and replies/posts that belong to ignored projects are skipped.</em><br /><br />",
		);
		$this->select_transfers($settings);
	}

	/// (6/7->7) Confirm settings. (out)
	private function confirm_settings_out()
	{
		if (isset($_POST['targetboard']))
		{
			$this->vars_out['configLocation'] = $_POST['targetboard'];
			$this->parse_configs();
		}
		else
		{
			unset($this->vars_out['configLocation']);
		}
		$this->confirm_settings($this->tracker_out,'tracker_projects',$this->vars_out,7);
	}

	/// (5/6->7) Ask for manual settings. (out)
	function ask_manual_settings_out()
	{
		$this->ask_manual_settings($this->tracker_out,'_out',7);
	}

	/// (5->6) Scan local dirs. (out)
	private function scan_local_out()
	{
		$this->scan_local($this->tracker_out,6);
	}

	/// (4->5) Ask for IPB location. (out)
	private function ask_location_ipb_out()
	{
		$this->ask_location_ipb($this->tracker_out,5);
	}

	/// (3/4->4) Confirm settings. (in)
	private function confirm_settings_in()
	{
		if (isset($_POST['targetboard']))
		{
			$this->vars_in['configLocation'] = $_POST['targetboard'];
			$this->parse_configs();
		}
		else
		{
			unset($this->vars_in['configLocation']);
		}
		$this->confirm_settings($this->tracker_in,'bugtracker_projects',$this->vars_in,4);
	}

	/// (2/3->4) Ask for manual settings. (in)
	function ask_manual_settings_in()
	{
		$this->ask_manual_settings($this->tracker_in,'_in',4);
	}

	/// (2->3) Scan local dirs. (in)
	private function scan_local_in()
	{
		$this->scan_local($this->tracker_in,3);
	}

	/// (1->2) Ask for IPB location. (in)
	private function ask_location_ipb_in()
	{
		$this->ask_location_ipb($this->tracker_in,2);
	}

	/// (0->1) Welcome message.
	private function display_welcome_message()
	{
		$str = "
<center><strong>Welcome to the tracker converter from <a href='".$this->url_in."' target='_blank'>".$this->tracker_in."</a> to
<a href='".$this->url_out."' target='_blank'>".$this->tracker_out.	"</a></strong></center><br />
Over the course of the next several minutes, the converter will attempt to locate your IP.Board installations
and pull database and configuration settings from its include files.<br /><br />

<em>If you are not comfortable with this, please close the converter now.</em><br /><br />

".$this->table_convert()."
It is assumed that the trackers have similar structures.<br /><br />

<input type='hidden' name='stage' value='1' />
<input type='submit' value='I Agree, Continue' />
";
		$this->output($str);
	}

	//----------------------------------------------------------------------
	// private multi-stage functions
	//----------------------------------------------------------------------

	/// (14->15) custom fields
	/// (12->13) severities
	/// (10->11) categories
	/// (8->9) projects
	private function confirm_transfers($settings,$map)
	{
		$this->parse_configs();
		$map_in = $this->sql_id2name($settings['tbl_in'],$settings['colId_in'],$settings['colName_in'],$this->vars_in);
		$map_out = $this->sql_id2name($settings['tbl_out'],$settings['colId_out'],$settings['colName_out'],$this->vars_out);

		$str = "
<center><em>".$this->tracker_in."</em> &rarr; <em>".$this->tracker_out."</em></center>
<center><strong>Confirm ".$settings['title']." Transfers</strong></center><br />".$settings['extraText']."
We will be making the following transfers: <br /><br />
<table>
";
		foreach ($map as $id_in => $id_out)
		{
			$str .= "<tr><th>".$map_in[$id_in]."</th><td>&rarr;</td><td>".$map_out[$id_out]."</td></tr>";		}
		$str .= "
</table>
<input type='hidden' name='stage' value='".$settings['stage']."' />
<input type='submit' value='Continue' />
";
		$this->output($str);
	}

	/// (13->14) custom fields
	/// (11->12) severities
	/// (9->10) categories
	/// (7->8) projects
	private function select_transfers($settings)
	{
		$this->parse_configs();
		$map_in = $this->sql_id2name($settings['tbl_in'],$settings['colId_in'],$settings['colName_in'],$this->vars_in);
		$map_out = $this->sql_id2name($settings['tbl_out'],$settings['colId_out'],$settings['colName_out'],$this->vars_out);

		$str = "
<center><em>".$this->tracker_in."</em> &rarr; <em>".$this->tracker_out."</em></center>
<center><strong>Select ".$settings['title']." Transfers</strong></center><br />".$settings['extraText']."
Select the transfers you want to make: <br /><br />
<input type='hidden' name='".$settings['fieldList']."' value='".implode(',',array_keys($map_in))."' />
<table>
";
		foreach ($map_in as $id_in => $name_in)
		{
			$found_match = false;
			$str .= "<tr><td>".$name_in."<br /><div class='indented'>";
			foreach ($map_out as $id_out => $name_out)
			{
				if ($name_in == $name_out)
				{
					$found_match = true;
					$str .= "<div class='positive'><input type='radio' name='".$settings['fieldPrefix'].$id_in."' value='".$id_out."' checked>".$name_out."</input></div>";
				}
				else
					$str .= "<input type='radio' name='".$settings['fieldPrefix'].$id_in."' value='".$id_out."'".$checked.">".$name_out."</input><br />";
			}
			if ($found_match)
				$str .= "<input type='radio' name='".$settings['fieldPrefix'].$id_in."' value=''><i>(ignore)</i></input><br />";
			else
				$str .= "<div class='negative'><input type='radio' name='".$settings['fieldPrefix'].$id_in."' value='' checked><i>(ignore)</i></input></div>";
			$str .= "</div></td></tr>";
		}
		$str .= "
</table>
<input type='hidden' name='stage' value='".$settings['stage']."' />
<input type='submit' value='Continue' />
";
		$this->output($str);
	}

	/// (6/7->7) out
	/// (3/4->4) in
	private function confirm_settings($tracker,$projectsTable,$settings,$stage)
	{
		$str = "
<center><em>".$tracker."</em></center>
<center><strong>Confirm Settings</strong></center><br />
We will be installing with the following settings: <br /><br />
<table>
<tr><th>Host Name</th><td>".$settings['dbHost']."</td></tr>
<tr><th>User Name</th><td>".$settings['dbUser']."</td></tr>
<tr><th>Database</th><td>".$settings['dbDatabase']."</td></tr>
<tr><th>Table Prefix</th><td>".$settings['dbPrefix']."</td></tr>
</table><br />";

		$count = $this->sql_count($projectsTable,$settings);
		if ($count > 0)
		{
			$str .= "<div class='positive'>I was able to successfully query for bug tracker projects using these parameters!</div><br /><br />";
		}
		else
		{
			$str .= "<div class='negative'>These parameters did not allow me to connect to query for bugtracker projects.</div><br /><br />";
		}

		$str .= "
<input type='hidden' name='stage' value='".$stage."' />

<input type='submit' name='saction' value='Continue' />&nbsp;&nbsp;
<input type='submit' name='saction' value='Enter Manual Settings' />
";
		$this->output($str);
	}

	/// (5/6->7) out
	/// (2/3->4) in
	private function ask_manual_settings($tracker,$postfix,$stage)
	{
		$str .= "
<center><em>".$tracker."</em></center>
<center><strong>Manual Setup</strong></center><br />
Even if the installations are not on the same server, it's not a problem. As long as you can provide the
appropriate connection and authentication information, we can still use the remote database, just not for 	auto-logon,
unless they are under the same primary domain.<br /><br />

<table>
<tr><th>Host Name</th><td><input type='text' name='dbHost".$postfix."' /></td></tr>
<tr><th>User Name</th><td><input type='text' name='dbUser".$postfix."' /></td></tr>
<tr><th>Password</th><td><input type='password' name='dbPass".$postfix."' /></td></tr>
<tr><th>Database</th><td><input type='text' name='dbDatabase".$postfix."' /></td></tr>
<tr><th>Table Prefix</th><td><input type='text' name='dbPrefix".$postfix."' /></td></tr>
</table>

<br />
<input type='hidden' name='stage' value='".$stage."' />
<input type='submit' value='Continue' />
";
		$this->output($str);
	}

	/// (5->6) out
	/// (2->3) in
	private function scan_local($tracker,$stage)
	{
		$installs = $this->scan_ipb_installs();
		$str = "
<center><em>".$tracker."</em></center>
<center><strong>Detected Installations</strong></center><br />
We found the following possible installation locations for the board:<br /><br />
";

		foreach ($installs as $si)
		{
			$str .= "
<div class='indented'>
<input type='radio' name='targetboard' value='".$si['file']."'>
<strong>".$si['name']."</strong><br />
URL: ".$si['url']."<br />
Base Folder: ".$si['folder']."<br />
Config File: ".$si['file']."
</input></div>";
		}

		$str .= "<br /><br />
<input type='radio' name='targetboard' value='OTHER'>My board is not listed here. I will manually enter the settings.</input><br /><br />
<input type='hidden' name='stage' value='".$stage."' />
<input type='submit' value='Continue' />";
		$this->output($str);
	}

	/// (4->5) out
	/// (1->2) in
	private function ask_location_ipb($tracker,$stage)
	{
		$str = "
<center><em>".$tracker."</em></center>
<center><strong>Where Is Your IPB Install?</strong></center><br />
If your IP.Board installation is on the same server, there is a good chance the converter can find it
and automatically detect all of the configuration options. If not, we can still use it, but you will need
to manually specify the set of credentials to connect.<br /><br />

<input type='radio' name='location' value='scan'>Scan local dirs.</input><br />
<input type='radio' name='location' value='manual'>Set connection settings manually.</input><br /><br />
<input type='hidden' name='stage' value='".$stage."' />
<input type='submit' value='Continue' />
";
		$this->output($str);
	}

	//----------------------------------------------------------------------
	// private utility functions
	//----------------------------------------------------------------------

	/// Return a sql connection.
	function sql_connection($settings)
	{
		$sql = mysql_connect($settings['dbHost'], $settings['dbUser'], $settings['dbPass']);
		mysql_select_db($settings['dbDatabase'], $sql);
		return $sql;
	}

	/// Generate an id => name map from sql.
	function sql_id2name($tbl,$colId,$colName,$settings,$conditions="")
	{
		$sql = $this->sql_connection($settings);
		$q = mysql_query("SELECT DISTINCT ".$colId.",".$colName." FROM ".$settings['dbPrefix'].$tbl." ".$conditions, $sql);

		$map = array();
		while ($row = mysql_fetch_assoc($q))
		{
			$map[$row[$colId]] = $row[$colName];
		}
		return $map;
	}

	/// Generate an id => row map from sql.
	function sql_id2row($tbl,$colId,$settings,$conditions="")
	{
		$sql = $this->sql_connection($settings);
		$q = mysql_query("SELECT * FROM ".$settings['dbPrefix'].$tbl." ".$conditions, $sql);
		$map = array();
		while ($row = mysql_fetch_assoc($q))
		{
			$map[$row[$colId]] = $row;
		}
		return $map;
	}

	/// Count the number of rows in a table.
	function sql_count($tbl,$settings,$conditions="")
	{
		$sql = $this->sql_connection($settings);
		$q = mysql_query("SELECT COUNT(*) as count FROM ".$settings['dbPrefix'].$tbl." ".$conditions, $sql);
		$row = mysql_fetch_assoc($q);
		return $row['count'];
	}

	/// Truncates a table.
	function sql_truncate($tbl,$settings)
	{
		$sql = $this->sql_connection($settings);
		$str = "TRUNCATE ".$settings['dbPrefix'].$tbl;
		$q = mysql_query($str,$sql);
	}

	/// Deletes from a table.
	function sql_delete($tbl,$settings,$conditions="")
	{
		$sql = $this->sql_connection($settings);
		$str = "DELETE FROM ".$settings['dbPrefix'].$tbl." ".$conditions;
		$q = mysql_query($str,$sql);
	}

	/// Insert a row into a table.
	function sql_insert($tbl,$row,$settings)
	{
		$sql = $this->sql_connection($settings);
		$cols = array();
		$vals = array();
		foreach ($row as $key => $value)
		{
			if ($value === NULL)
				$value = 'NULL';
			else
				$value = "'".mysql_real_escape_string($value)."'";
			array_push($cols,$key);
			array_push($vals,$value);
		}
		$str = "INSERT INTO ".$settings['dbPrefix'].$tbl." (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
		$q = mysql_query($str,$sql);
		return mysql_insert_id($sql);
	}

	/// Update a row in a table.
	function sql_update($tbl,$row,$settings,$conditions="")
	{
		$sql = $this->sql_connection($settings);
		$sets = array();
		foreach ($row as $key => $value)
		{
			if ($value === NULL)
				$value = 'NULL';
			else
				$value = "'".mysql_real_escape_string($value)."'";
			array_push($sets,$key."=".$value);
		}
		$str = "UPDATE ".$settings['dbPrefix'].$tbl." SET ".implode(',',$sets)." ".$conditions;
		$q = mysql_query($str,$sql);
	}

	/// Parse IPB configs into vars.
	private function parse_configs()
	{
		if (isset($this->vars_in['configLocation']))
		{
			$settings = $this->parse_in($this->vars_in['configLocation']);
			if (isset($settings['sql_port']))
				$this->vars_in['dbHost'] = $settings['sql_host'].":".$settings['sql_port'];
			else
				$this->vars_in['dbHost'] = $settings['sql_host'];
			$this->vars_in['dbPass'] = $settings['sql_pass'];
			$this->vars_in['dbUser'] = $settings['sql_user'];
			$this->vars_in['dbPrefix'] = $settings['sql_tbl_prefix'];
			$this->vars_in['dbDatabase'] = $settings['sql_database'];
		}
		if (isset($this->vars_out['configLocation']))
		{
			$settings = $this->parse_in($this->vars_out['configLocation']);
			if (isset($settings['sql_port']))
				$this->vars_out['dbHost'] = $settings['sql_host'].":".$settings['sql_port'];
			else
				$this->vars_out['dbHost'] = $settings['sql_host'];
			$this->vars_out['dbPass'] = $settings['sql_pass'];
			$this->vars_out['dbUser'] = $settings['sql_user'];
			$this->vars_out['dbPrefix'] = $settings['sql_tbl_prefix'];
			$this->vars_out['dbDatabase'] = $settings['sql_database'];
		}
	}

	// Filter db variables.
	private function filter_db_variables($vars)
	{
		$db_fields = array('dbHost','dbPass','dbUser','dbPrefix','dbDatabase');
		$arr = $vars;
		if (isset($arr['configLocation']) and $arr['configLocation'])
			foreach ($db_fields as $field)
				unset($arr[$field]);
		return $arr;
	}

	// Get hidden fields of variables.
	private function hidden_variables()
	{
		$str = "";
		// in
		foreach ($this->filter_db_variables($this->vars_in) as $field => $value)
			$str .= "<input type='hidden' name='".$field."_in' value='".$value."' />";
		// out
		foreach ($this->filter_db_variables($this->vars_out) as $field => $value)
			$str .= "<input type='hidden' name='".$field."_out' value='".$value."' />";
		// projects
		if (count($this->projects) > 0)
		{
			$str .= "<input type='hidden' name='projects' value='".implode(',',array_keys($this->projects))."' />";
			foreach ($this->projects as $id_in => $id_out)
				$str .= "<input type='hidden' name='project_".$id_in."' value='".$id_out."' />";
		}
		// categories
		if (count($this->categories) > 0)
		{
			$str .= "<input type='hidden' name='categories' value='".implode(',',array_keys($this->categories))."' />";
			foreach ($this->categories as $id_in => $id_out)
				$str .= "<input type='hidden' name='category_".$id_in."' value='".$id_out."' />";
		}
		// severities
		if (count($this->severities) > 0)
		{
			$str .= "<input type='hidden' name='severities' value='".implode(',',array_keys($this->severities))."' />";
			foreach ($this->severities as $id_in => $id_out)
				$str .= "<input type='hidden' name='severity_".$id_in."' value='".$id_out."' />";
		}
		// custom fields
		if (count($this->custom_fields) > 0)
		{
			$str .= "<input type='hidden' name='custom_fields' value='".implode(',',array_keys($this->custom_fields))."' />";
			foreach ($this->custom_fields as $id_in => $id_out)
				$str .= "<input type='hidden' name='custom_field_".$id_in."' value='".$id_out."' />";
		}
		// options
		if (count($this->options) > 0)
		{
			$str .= "<input type='hidden' name='options' value='".implode(',',array_keys($this->options))."' />";
			foreach ($this->options as $name => $value)
				$str .= "<input type='hidden' name='option_".$name."' value='".$value."' />";
		}
		return $str;
	}

	/// Parse variables into the converter.
	private function parse_variables($vars)
	{
		$db_fields = array('dbHost','dbPass','dbUser','dbPrefix','dbDatabase');
		// in
		if (isset($vars['configLocation_in']))
			$this->vars_in['configLocation'] = $vars['configLocation_in'];
		else foreach ($db_fields as $field)
			if (isset($vars[$field.'_in'])) $this->vars_in[$field] = $vars[$field.'_in'];
		// out
		if (isset($vars['configLocation_out']))
			$this->vars_out['configLocation'] = $vars['configLocation_out'];
		else foreach ($db_fields as $field)
			if (isset($vars[$field.'_out'])) $this->vars_out[$field] = $vars[$field.'_out'];
		// projects
		if (isset($vars['projects']))
			foreach (explode(',',$vars['projects']) as $id)
				$this->projects[$id] = $vars['project_'.$id];
		// categories
		if (isset($vars['categories']))
			foreach (explode(',',$vars['categories']) as $id)
				$this->categories[$id] = $vars['category_'.$id];
		// severities
		if (isset($vars['severities']))
			foreach (explode(',',$vars['severities']) as $id)
				$this->severities[$id] = $vars['severity_'.$id];
		// custom fields
		if (isset($vars['custom_fields']))
			foreach (explode(',',$vars['custom_fields']) as $id)
				$this->custom_fields[$id] = $vars['custom_field_'.$id];
		// options
		if (isset($vars['options']))
			foreach (explode(',',$vars['options']) as $id)
				$this->options[$id] = $vars['option_'.$id];
	}

	/// Output content.
	private function output($str)
	{
		$out = "<form method='POST' action='converter.php'>";
		$out .= $this->hidden_variables();
		$out .= $str;
		$out .= "</form>";
		$tplFile = file_get_contents("converter.tpl");
		echo str_replace("[[%CONTENT%]]", $out, $tplFile);
	}

	/// Output content that auto-submits itself.
	private function outputAutosubmit($str)
	{
		$out = "
<form method='POST' action='converter.php' name='autosubmit' id='autosubmit'>
".$this->hidden_variables().$str."
<script language='JavaScript'>
function do_submit() { document.getElementById('autosubmit').submit(); }
var time = setTimeout('do_submit()',2000);
document.write(\"<br /><a href='javascript:do_submit()'><i>pausing for 2 seconds, click here to continue now</i></a>\");
</script>
<noscript><br /><input type='submit' value='Continue (javascript is disabled)' /></noscript>
</form>";
		$tplFile = file_get_contents("converter.tpl");
		echo str_replace("[[%CONTENT%]]", $out, $tplFile);
	}

	/// Scan for IPB instalations.
	private function scan_ipb_installs()
	{
		$result = array();

		// Do a scan up to 3 levels deep for files
		for ($i = 1 /*3*/; $i >= 1; $i--)
		{
			$pathStr = '..';
			for ($j = 0; $j < $i; $j++) { $pathStr .= '/..'; }
			$files = $this->find_files($pathStr, 'conf_global.php');
			if (sizeof($files) > 0) { break; }
		}

		foreach ($files as $configFile)
		{
			$data["file"] = $configFile;
			$confData = $this->parse_in($configFile);
			$data["name"] = $confData['board_name'];
			$data["url"] = $confData['board_url'];
			$data["folder"] = $confData['base_dir'];
			array_push($result, $data);
		}
		return $result;
	}

	//----------------------------------------------------------------------
	// private external functions
	//----------------------------------------------------------------------

	/// Find config files.
	private function find_files($dir, $pattern)
	{  
		$files = glob("$dir/$pattern");   
		foreach (glob("$dir/{.[^.]*,*}", GLOB_BRACE|GLOB_ONLYDIR) as $sub_dir){  
			$arr   = $this->find_files($sub_dir, $pattern);  
			$files = array_merge($files, $arr);  
		}
		return $files;  
	}

	/// Parse IPB configs.
	function parse_in($file)
	{
		$str = file_get_contents($file);
		$str = explode("\n", $str);

		$result = array();

		foreach ($str as $line)
		{
			$raw = explode('=', $line);
			$varName = trim($raw[0]);
			$varVal = trim($raw[1]);

			$varName = str_replace('$INFO[\'', '', $varName);
			$varName = str_replace('\']', '', $varName);

			$varVal = substr($varVal, 1, strlen($varVal) - 3);

			$result[$varName] = $varVal;
		}

		return $result;
	}

}

/**
* IPSText
*
* This deals with cleaning and parsing text items.
* @taken_from IPB 3.1.4
*/
class IPSText
{
	/**
	 * Default document character set
	 *
	 * @var		string		Character set
	 */
	static public $gb_char_set = 'UTF-8';

	/**
	 * Ensure no one can create this as an object
	 *
	 * @return	void
	 */
	private function __construct() {}

	/**
	 * Make an SEO title for use in the URL
	 * We parse them even if friendly urls are off so that the data is there when you do switch it on
	 *
	 * @param	string		Raw SEO title or text
	 * @return	string		Cleaned up SEO title
	 */
	static public function makeSeoTitle( $text )
	{
		if ( ! $text )
		{
			return '';
		}

		/* Strip all HTML tags first */
		$text = strip_tags($text);

		/* Preserve %data */
		$text = preg_replace('#%([a-fA-F0-9][a-fA-F0-9])#', '-xx-$1-xx-', $text);
		$text = str_replace( array( '%', '`' ), '', $text);
		$text = preg_replace('#-xx-([a-fA-F0-9][a-fA-F0-9])-xx-#', '%$1', $text);

		/* Convert accented chars */
		$text = self::convertAccents($text);

		/* Convert it */
		if ( self::isUTF8( $text )  )
		{
			if ( function_exists('mb_strtolower') )
			{
				$text = mb_strtolower($text, 'UTF-8');
			}

			$text = self::utf8Encode( $text, 250 );
		}

		/* Finish off */
		$text = strtolower($text);

		if ( strtolower( self::$gb_char_set ) == 'utf-8' )
		{
			$text = preg_replace( '#&.+?;#'        , '', $text );
			$text = preg_replace( '#[^%a-z0-9 _-]#', '', $text );
		}
		else
		{
			/* Remove &#xx; and &#xxx; but keep &#xxxx; */
			$text = preg_replace( '/&#(\d){2,3};/', '', $text );
			$text = preg_replace( '#[^%&\#;a-z0-9 _-]#', '', $text );
			$text = str_replace( array( '&quot;', '&amp;'), '', $text );
		}

		$text = str_replace( array( '`', ' ', '+', '.', '?', '_', '#' ), '-', $text );
		$text = preg_replace( "#-{2,}#", '-', $text );
		$text = trim($text, '-');

		return ( $text ) ? $text : '-';
	}

	/**
	 * Seems like UTF-8?
	 * hmdker at gmail dot com {@link php.net/utf8_encode}
	 *
	 * @param	string		Raw text
	 * @return	boolean
	 */
	static public function isUTF8($str) {
	    $c=0; $b=0;
	    $bits=0;
	    $len=strlen($str);
	    for($i=0; $i<$len; $i++)
	    {
	        $c=ord($str[$i]);

	        if($c > 128)
	        {
	            if(($c >= 254)) return false;
	            elseif($c >= 252) $bits=6;
	            elseif($c >= 248) $bits=5;
	            elseif($c >= 240) $bits=4;
	            elseif($c >= 224) $bits=3;
	            elseif($c >= 192) $bits=2;
	            else return false;

	            if(($i+$bits) > $len) return false;

	            while( $bits > 1 )
	            {
	                $i++;
	                $b = ord($str[$i]);
	                if($b < 128 || $b > 191) return false;
	                $bits--;
	            }
	        }
	    }

	    return true;
	}

	/**
	 * Converts accented characters into their plain alphabetic counterparts
	 *
	 * @param	string		Raw text
	 * @return	string		Cleaned text
	 */
	static public function convertAccents($string)
	{
		if ( ! preg_match('/[\x80-\xff]/', $string) )
		{
			return $string;
		}

		if ( self::isUTF8( $string) )
		{
			$_chr = array(
							/* Latin-1 Supplement */
							chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
							chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
							chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
							chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
							chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
							chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
							chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
							chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
							chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
							chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
							chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
							chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
							chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
							chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
							chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
							chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
							chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
							chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
							chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
							chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
							chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
							chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
							chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
							chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
							chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
							chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
							chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
							chr(195).chr(191) => 'y',
							/* Latin Extended-A */
							chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
							chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
							chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
							chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
							chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
							chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
							chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
							chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
							chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
							chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
							chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
							chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
							chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
							chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
							chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
							chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
							chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
							chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
							chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
							chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
							chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
							chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
							chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
							chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
							chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
							chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
							chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
							chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
							chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
							chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
							chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
							chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
							chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
							chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
							chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
							chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
							chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
							chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
							chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
							chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
							chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
							chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
							chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
							chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
							chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
							chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
							chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
							chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
							chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
							chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
							chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
							chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
							chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
							chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
							chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
							chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
							chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
							chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
							chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
							chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
							chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
							chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
							chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
							chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
							/* Euro Sign */
							chr(226).chr(130).chr(172) => 'E',
							/* GBP (Pound) Sign */
							chr(194).chr(163) => '' );

			$string = strtr($string, $_chr);
		}
		else
		{
			$_chr      = array();
			$_dblChars = array();
			
			/* We assume ISO-8859-1 if not UTF-8 */
			$_chr['in'] =   chr(128).chr(131).chr(138).chr(142).chr(154).chr(158)
							.chr(159).chr(162).chr(165).chr(181).chr(192).chr(193).chr(194)
							.chr(195).chr(199).chr(200).chr(201).chr(202)
							.chr(203).chr(204).chr(205).chr(206).chr(207).chr(209).chr(210)
							.chr(211).chr(212).chr(213).chr(217).chr(218)
							.chr(219).chr(220).chr(221).chr(224).chr(225).chr(226).chr(227)
							.chr(231).chr(232).chr(233).chr(234).chr(235)
							.chr(236).chr(237).chr(238).chr(239).chr(241).chr(242).chr(243)
							.chr(244).chr(245).chr(249).chr(250).chr(251)
							.chr(252).chr(253).chr(255).chr(191).chr(182).chr(179).chr(166)
							.chr(230).chr(198).chr(175).chr(172).chr(188)
							.chr(163).chr(161).chr(177);

			$_chr['out'] = "EfSZszYcYuAAAACEEEEIIIINOOOOUUUUYaaaaceeeeiiiinoooouuuuyyzslScCZZzLAa";

			$string           = strtr( $string, $_chr['in'], $_chr['out'] );
			$_dblChars['in']  = array( chr(140), chr(156), chr(196), chr(197), chr(198), chr(208), chr(214), chr(216), chr(222), chr(223), chr(228), chr(229), chr(230), chr(240), chr(246), chr(248), chr(254));
			$_dblChars['out'] = array('Oe', 'oe', 'Ae', 'Aa', 'Ae', 'DH', 'Oe', 'Oe', 'TH', 'ss', 'ae', 'aa', 'ae', 'dh', 'oe', 'oe', 'th');
			$string           = str_replace($_dblChars['in'], $_dblChars['out'], $string);
		}
				
		return $string;
	}

	/**
	 * Manually utf8 encode to a specific length
	 * Based on notes found at php.net
	 *
	 * @param	string		Raw text
	 * @param	int			Length
	 * @return	string
	 */
	static public function utf8Encode( $string, $len=0 )
	{
		$_unicode       = '';
		$_values        = array();
		$_nOctets       = 1;
		$_unicodeLength = 0;
 		$stringLength   = strlen( $string );

		for ( $i = 0 ; $i < $stringLength ; $i++ )
		{
			$value = ord( $string[ $i ] );

			if ( $value < 128 )
			{
				if ( $len && ( $_unicodeLength >= $len ) )
				{
					break;
				}

				$_unicode .= chr($value);
				$_unicodeLength++;
			}
			else
			{
				if ( count( $_values ) == 0 )
				{
					$_nOctets = ( $value < 224 ) ? 2 : 3;
				}

				$_values[] = $value;

				if ( $len && ( $_unicodeLength + ($_nOctets * 3) ) > $len )
				{
					break;
				}

				if ( count( $_values ) == $_nOctets )
				{
					if ( $_nOctets == 3 )
					{
						$_unicode .= '%' . dechex($_values[0]) . '%' . dechex($_values[1]) . '%' . dechex($_values[2]);
						$_unicodeLength += 9;
					}
					else
					{
						$_unicode .= '%' . dechex($_values[0]) . '%' . dechex($_values[1]);
						$_unicodeLength += 6;
					}

					$_values  = array();
					$_nOctets = 1;
				}
			}
		}

		return $_unicode;
	}
}

$converter = new Converter();
$converter->run();
