<?php
/**
 * @author           Suat Secmen (https://su.at)
 * @copyright        2016 Suat Secmen
 * @license          MIT License <https://su.at/mit>
 */
require_once __DIR__.'/Lang.abstract.php';
class PhpLang extends Lang {
	protected $code, $rest, $keys;
	
	/**
	 * @see Lang.abstract.php -> __construct()
	 */
	function __construct($code) {
		parent::__construct($code);
	}
	
	/**
	 * @see Lang.abstract.php -> run()
	 */
	public function run() {
		$this->rest = ' '.$this->code.' ';
		$this->keys['demerit'] = -2;
		
		$this->keys['stringsLen'] = 0;
		$this->keys['commentsLen'] = 0;
		
		// scan the whole string (this was the safest solution)
		$this->removeStringsAndComments();
		// escapes
		$this->rest = preg_replace('~\\\\.~', '', $this->rest); // remove escape after string&comment removal, else /* \*/ will be buggy
		// no html (js code looks like php sometimes, so let's remove scripts)
		$this->rest = preg_replace_callback('~<script[^>]*>.*</script>~is', function($m) {
			$this->keys['demerit'] += strlen($m[0]);
			return '';
		}, $this->rest);
		// curly brace whitespace
		$this->rest = preg_replace_callback('~\s+\{~i', function($m) {
			$this->keys['demerit'] += strlen($m[0]) - 1;
			return '{';
		}, $this->rest);
		// invalidate on some css or js like: '[attr]{' or '[{' or '{a:{'
		if (preg_match('~[\]\[\:]\{~', $this->rest)) {
			$this->errors = true;
			return;
		}
		// whitespaces
		$this->rest = preg_replace_callback('~ +~', function($m) {
			$this->keys['demerit'] += strlen($m[0]) - 1;
			return ' ';
		}, $this->rest);
		// nowdoc/heredoc
		$this->rest = preg_replace_callback('~<<<\s*(\'|)([a-z0-9]+)\1\n.*\n\2\;~siU', function($m) {
			$this->keys['keywords']++;
			$this->keys['keywordsLen'] += 4 + (isset($m[2]) ? strlen($m[2]) : strlen($m[1])); // 4 = <<<;, just a little bonus
			$this->keys['stringsLen'] += strlen($m[0]);
			return '';
		}, $this->rest);
		// classes, methods, functions
		$this->rest = preg_replace_callback('~\b(new|class) \w+~', function($m) {
			$this->keys['keywords']++;
			$this->keys['keywordsLen'] += strlen($m[1]);
			return '';
		}, $this->rest);
		$this->rest = preg_replace('~\b\w+\:\:\w+~', '', $this->rest);
		$this->rest = preg_replace_callback('~(\$\w+\->|function\s+)(\w+)\s*\(~', function($m) {
			if (trim($m[1]) == 'function') {
				$this->keys['keywords']++;
				$this->keys['keywordsLen'] += 8;
			} elseif ($m[1] == '$this->') {
				$this->keys['keywords']++;
				$this->keys['keywordsLen'] += 7;
			}
			if (preg_match('~^__(construct|destruct|call|callStatic|get|set|isset|unset|sleep|wakeup|toString|set_state|clone|debugInfo)$~', $m[2])) {
				$this->keys['keywords']++;
				$this->keys['keywordsLen'] += strlen($m[2]);
			}
			return '';
		}, $this->rest);
		// variables
		$this->rest = preg_replace('~\&?\$[a-z_]\w*~i', '', $this->rest);
		// keywords
		$keywords = $this->keywords();
		foreach ($keywords as $k) {
			$this->rest = preg_replace_callback('~(\W)(\@?'.$this->allowUppercase(preg_quote($k)).')\s*(\([^\)]*\))?\b~', function($m) {
				$this->keys['keywords']++;
				$this->keys['keywordsLen'] += strlen($m[2]);
				return $m[1];
			}, $this->rest);
		}
		// functions
		$this->rest = preg_replace('~(\W)\w+\([^\)]*\)\s*(;|\{)~i', '$1', $this->rest);
		// special chars
		$this->rest = preg_replace('~(\&=|/|\*|=|;|\(|\)|\[|\]|\{|\}|\&\&|\s\&\s|\|\||\@|\.|,|\?|\:|\!|\-|<|>|\+|\d*\.\d+e?|\d+\.\d*e?|0x\d+|\d+e?)~i', ' ', $this->rest);
		// remove whitespace
		$this->rest = preg_replace_callback('~\s+~', function($m) {
			$this->keys['demerit'] += strlen($m[0]);
			return '';
		}, $this->rest);
	}
	
	/**
	 * @see Lang.abstract.php -> demerit()
	 */
	public function demerit() {
		return $this->keys['stringsLen'] + $this->keys['demerit'];
	}
	
	/**
	 * this function allows lower case letters also match upper cases, but not upper cases to match lower cases (constants)
	 */
	private function allowUppercase($str) {
		$r = '';
		$letters = 'abcdefghijklmnopqrstuvwxyz';
		for ($i = 0, $l = strlen($str); $i < $l; $i++) {
			if (strpos($letters, $str[$i]) !== false) {
				$r .= '['.$str[$i].strtoupper($str[$i]).']';
			} else $r .= $str[$i];
		}
		return $r;
	}
	
	/**
	 * scan the whole string (this was the safest solution)
	 */
	private function removeStringsAndComments() {
		$inString = false;
		$inComment = false;
		for ($i = 0, $l = strlen($this->rest); $i < $l; $i++) {
			// skip if escaped, except you're in a comment
			// '/* \*/' or '// eol: \' <- don't escape
			if ($this->rest[$i] == '\\' && !$inComment) {
				$i++;
				continue;
			}
			if ($inString) {
				if ($this->rest[$i] == $inString) {
					// string finished
					$len = $i - $start + strlen($inString);
					$this->rest = substr($this->rest, 0, $start).substr($this->rest, $i + strlen($inString));
					$inString = 0;
					$i -= $len - (strlen($inString) - 1);
					$l -= $len;
					$this->keys['stringsLen'] += $len;
				}
			} elseif ($inComment) {
				if ($this->rest[$i] == $inComment[0] && (strlen($inComment) == 1 || strlen($inComment) == 2 && $this->rest[$i + 1] == $inComment[1])) {
					// comment finished
					$len = $i - $start + strlen($inComment);
					$this->rest = substr($this->rest, 0, $start).substr($this->rest, $i + strlen($inComment));
					$inComment = 0;
					$i -= $len - (strlen($inComment) - 1);
					$l -= $len;
					$this->keys['commentsLen'] += $len;
				}
			} else {
				if ($this->rest[$i] == '\'' || $this->rest[$i] == '"') {
					$inString = $this->rest[$i];
					$start = $i;
				} elseif ($this->rest[$i] == '#' || $this->rest[$i] == '/' && ($this->rest[$i + 1] == '*' || $this->rest[$i + 1] == '/')) {
					$inComment = $this->rest[$i] == '/' && $this->rest[$i + 1] == '*' ? '*/' : "\n";
					$start = $i;
					if ($this->rest[$i] !== '#') $i++;
				}
			}
		}
		return $this->rest;
	}
	
	/**
	 * php keywords (system functions, constants, ...)
	 */
	private function keywords() {
		return [
			'abs','acos','acosh','addcslashes','addslashes','aggregate','aggregate_methods','aggregate_methods_by_list','aggregate_methods_by_regexp',
			'aggregate_properties','aggregate_properties_by_list','aggregate_properties_by_regexp','aggregation_info','apache_child_terminate','apache_get_modules',
			'apache_get_version','apache_getenv','apache_lookup_uri','apache_note','apache_request_headers','apache_response_headers','apache_setenv','array',
			'array_change_key_case','array_chunk','array_combine','array_count_values','array_diff','array_diff_assoc','array_diff_key','array_diff_uassoc',
			'array_diff_ukey','array_fill','array_fill_keys','array_filter','array_flip','array_intersect','array_intersect_assoc','array_intersect_key',
			'array_intersect_uassoc','array_intersect_ukey','array_key_exists','array_keys','array_map','array_merge','array_merge_recursive','array_multisort',
			'array_pad','array_pop','array_product','array_push','array_rand','array_reduce','array_reverse','array_search','array_shift','array_slice',
			'array_splice','array_sum','array_udiff','array_udiff_assoc','array_udiff_uassoc','array_uintersect','array_uintersect_assoc','array_uintersect_uassoc',
			'array_unique','array_unshift','array_values','array_walk','array_walk_recursive','arsort','asin','asinh','asort','assert','assert_options','atan',
			'atan2','atanh','base_convert','base64_decode','base64_encode','basename','bcadd','bccomp','bcdiv','bcmod','bcmul','bcompiler_load','bcompiler_load_exe',
			'bcompiler_parse_class','bcompiler_read','bcompiler_write_class','bcompiler_write_constant','bcompiler_write_exe_footer','bcompiler_write_file',
			'bcompiler_write_footer','bcompiler_write_function','bcompiler_write_functions_from_file','bcompiler_write_header','bcompiler_write_included_filename',
			'bcpow','bcpowmod','bcscale','bcsqrt','bcsub','bin2hex','bindec','bindtextdomain','bind_textdomain_codeset','bitset_empty','bitset_equal','bitset_excl',
			'bitset_fill','bitset_from_array','bitset_from_hash','bitset_from_string','bitset_in','bitset_incl','bitset_intersection','bitset_invert',
			'bitset_is_empty','bitset_subset','bitset_to_array','bitset_to_hash','bitset_to_string','bitset_union','blenc_encrypt','bzclose','bzcompress',
			'bzdecompress','bzerrno','bzerror','bzerrstr','bzflush','bzopen','bzread','bzwrite','cal_days_in_month','cal_from_jd','cal_info','cal_to_jd',
			'call_user_func','call_user_func_array','call_user_method','call_user_method_array','ceil','chdir','checkdate','checkdnsrr','chgrp','chmod','chop',
			'chown','chr','chunk_split','class_exists','class_implements','class_parents','classkit_aggregate_methods','classkit_doc_comments','classkit_import',
			'classkit_method_add','classkit_method_copy','classkit_method_redefine','classkit_method_remove','classkit_method_rename','clearstatcache','closedir',
			'closelog','com_create_guid','com_event_sink','com_get_active_object','com_load_typelib','com_message_pump','com_print_typeinfo','compact',
			'confirm_phpdoc_compiled','connection_aborted','connection_status','constant','convert_cyr_string','convert_uudecode','convert_uuencode','copy','cos',
			'cosh','count','count_chars','cpdf_add_annotation','cpdf_add_outline','cpdf_arc','cpdf_begin_text','cpdf_circle','cpdf_clip','cpdf_close',
			'cpdf_closepath','cpdf_closepath_fill_stroke','cpdf_closepath_stroke','cpdf_continue_text','cpdf_curveto','cpdf_end_text','cpdf_fill',
			'cpdf_fill_stroke','cpdf_finalize','cpdf_finalize_page','cpdf_global_set_document_limits','cpdf_import_jpeg','cpdf_lineto','cpdf_moveto','cpdf_newpath',
			'cpdf_open','cpdf_output_buffer','cpdf_page_init','cpdf_rect','cpdf_restore','cpdf_rlineto','cpdf_rmoveto','cpdf_rotate','cpdf_rotate_text','cpdf_save',
			'cpdf_save_to_file','cpdf_scale','cpdf_set_action_url','cpdf_set_char_spacing','cpdf_set_creator','cpdf_set_current_page','cpdf_set_font',
			'cpdf_set_font_directories','cpdf_set_font_map_file','cpdf_set_horiz_scaling','cpdf_set_keywords','cpdf_set_leading','cpdf_set_page_animation',
			'cpdf_set_subject','cpdf_set_text_matrix','cpdf_set_text_pos','cpdf_set_text_rendering','cpdf_set_text_rise','cpdf_set_title',
			'cpdf_set_viewer_preferences','cpdf_set_word_spacing','cpdf_setdash','cpdf_setflat','cpdf_setgray','cpdf_setgray_fill','cpdf_setgray_stroke',
			'cpdf_setlinecap','cpdf_setlinejoin','cpdf_setlinewidth','cpdf_setmiterlimit','cpdf_setrgbcolor','cpdf_setrgbcolor_fill','cpdf_setrgbcolor_stroke',
			'cpdf_show','cpdf_show_xy','cpdf_stringwidth','cpdf_stroke','cpdf_text','cpdf_translate','crack_check','crack_closedict','crack_getlastmessage',
			'crack_opendict','crc32','create_function','crypt','ctype_alnum','ctype_alpha','ctype_cntrl','ctype_digit','ctype_graph','ctype_lower','ctype_print',
			'ctype_punct','ctype_space','ctype_upper','ctype_xdigit','curl_close','curl_copy_handle','curl_errno','curl_error','curl_exec','curl_getinfo',
			'curl_init','curl_multi_add_handle','curl_multi_close','curl_multi_exec','curl_multi_getcontent','curl_multi_info_read','curl_multi_init',
			'curl_multi_remove_handle','curl_multi_select','curl_setopt','curl_setopt_array','curl_version','current','cvsclient_connect','cvsclient_log',
			'cvsclient_login','cvsclient_retrieve','date','date_create','date_date_set','date_default_timezone_get','date_default_timezone_set','date_format',
			'date_isodate_set','date_modify','date_offset_get','date_parse','date_sun_info','date_sunrise','date_sunset','date_time_set','date_timezone_get',
			'date_timezone_set','db_id_list','dba_close','dba_delete','dba_exists','dba_fetch','dba_firstkey','dba_handlers','dba_insert','dba_key_split',
			'dba_list','dba_nextkey','dba_open','dba_optimize','dba_popen','dba_replace','dba_sync','dbase_add_record','dbase_close','dbase_create',
			'dbase_delete_record','dbase_get_header_info','dbase_get_record','dbase_get_record_with_names','dbase_numfields','dbase_numrecords','dbase_open',
			'dbase_pack','dbase_replace_record','dbg_get_all_contexts','dbg_get_all_module_names','dbg_get_all_source_lines','dbg_get_context_name',
			'dbg_get_module_name','dbg_get_profiler_results','dbg_get_source_context','dblist','dbmclose','dbmdelete','dbmexists','dbmfetch','dbmfirstkey',
			'dbminsert','dbmnextkey','dbmopen','dbmreplace','dbx_close','dbx_compare','dbx_connect','dbx_error','dbx_escape_string','dbx_fetch_row','dbx_query',
			'dbx_sort','dcgettext','dcngettext','deaggregate','debug_backtrace','debug_zval_dump','debugbreak','decbin','dechex','decoct','define','defined',
			'define_syslog_variables','deg2rad','dgettext','dio_close','dio_open','dio_read','dio_seek','dio_stat','dio_write','dir','dirname','disk_free_space',
			'disk_total_space','diskfreespace','dl','dngettext','docblock_token_name','docblock_tokenize','dom_import_simplexml','domxml_add_root',
			'domxml_attributes','domxml_children','domxml_doc_add_root','domxml_doc_document_element','domxml_doc_get_element_by_id',
			'domxml_doc_get_elements_by_tagname','domxml_doc_get_root','domxml_doc_set_root','domxml_doc_validate','domxml_doc_xinclude','domxml_dump_mem',
			'domxml_dump_mem_file','domxml_dump_node','domxml_dumpmem','domxml_elem_get_attribute','domxml_elem_set_attribute','domxml_get_attribute',
			'domxml_getattr','domxml_html_dump_mem','domxml_new_child','domxml_new_doc','domxml_new_xmldoc','domxml_node','domxml_node_add_namespace',
			'domxml_node_attributes','domxml_node_children','domxml_node_get_content','domxml_node_has_attributes','domxml_node_new_child',
			'domxml_node_set_content','domxml_node_set_namespace','domxml_node_unlink_node','domxml_open_file','domxml_open_mem','domxml_parser',
			'domxml_parser_add_chunk','domxml_parser_cdata_section','domxml_parser_characters','domxml_parser_comment','domxml_parser_end',
			'domxml_parser_end_document','domxml_parser_end_element','domxml_parser_entity_reference','domxml_parser_get_document','domxml_parser_namespace_decl',
			'domxml_parser_processing_instruction','domxml_parser_start_document','domxml_parser_start_element','domxml_root','domxml_set_attribute',
			'domxml_setattr','domxml_substitute_entities_default','domxml_unlink_node','domxml_version','domxml_xmltree','doubleval','each','easter_date',
			'easter_days','empty','end','ereg','ereg_replace','eregi','eregi_replace','error_get_last','error_log','error_reporting','escapeshellarg',
			'escapeshellcmd','eval','event_deschedule','event_dispatch','event_free','event_handle_signal','event_have_events','event_init','event_new',
			'event_pending','event_priority_set','event_schedule','event_set','event_timeout','exec','exif_imagetype','exif_read_data','exif_tagname',
			'exif_thumbnail','exp','explode','expm1','extension_loaded','extract','ezmlm_hash','fbird_add_user','fbird_affected_rows','fbird_backup',
			'fbird_blob_add','fbird_blob_cancel','fbird_blob_close','fbird_blob_create','fbird_blob_echo','fbird_blob_get','fbird_blob_import','fbird_blob_info',
			'fbird_blob_open','fbird_close','fbird_commit','fbird_commit_ret','fbird_connect','fbird_db_info','fbird_delete_user','fbird_drop_db','fbird_errcode',
			'fbird_errmsg','fbird_execute','fbird_fetch_assoc','fbird_fetch_object','fbird_fetch_row','fbird_field_info','fbird_free_event_handler',
			'fbird_free_query','fbird_free_result','fbird_gen_id','fbird_maintain_db','fbird_modify_user','fbird_name_result','fbird_num_fields',
			'fbird_num_params','fbird_param_info','fbird_pconnect','fbird_prepare','fbird_query','fbird_restore','fbird_rollback','fbird_rollback_ret',
			'fbird_server_info','fbird_service_attach','fbird_service_detach','fbird_set_event_handler','fbird_trans','fbird_wait_event','fclose',
			'fdf_add_doc_javascript','fdf_add_template','fdf_close','fdf_create','fdf_enum_values','fdf_errno','fdf_error','fdf_get_ap','fdf_get_attachment',
			'fdf_get_encoding','fdf_get_file','fdf_get_flags','fdf_get_opt','fdf_get_status','fdf_get_value','fdf_get_version','fdf_header','fdf_next_field_name',
			'fdf_open','fdf_open_string','fdf_remove_item','fdf_save','fdf_save_string','fdf_set_ap','fdf_set_encoding','fdf_set_file','fdf_set_flags',
			'fdf_set_javascript_action','fdf_set_on_import_javascript','fdf_set_opt','fdf_set_status','fdf_set_submit_form_action','fdf_set_target_frame',
			'fdf_set_value','fdf_set_version','feof','fflush','fgetc','fgetcsv','fgets','fgetss','file','file_exists','file_get_contents','file_put_contents',
			'fileatime','filectime','filegroup','fileinode','filemtime','fileowner','fileperms','filepro','filepro_fieldcount','filepro_fieldname',
			'filepro_fieldtype','filepro_fieldwidth','filepro_retrieve','filepro_rowcount','filesize','filetype','filter_has_var','filter_id','filter_input',
			'filter_input_array','filter_list','filter_var','filter_var_array','finfo_buffer','finfo_close','finfo_file','finfo_open','finfo_set_flags',
			'floatval','flock','floor','flush','fmod','fnmatch','fopen','fpassthru','fprintf','fputcsv','fputs','fread','frenchtojd','fribidi_charset_info',
			'fribidi_get_charsets','fribidi_log2vis','fscanf','fseek','fsockopen','fstat','ftell','ftok','ftp_alloc','ftp_cdup','ftp_chdir','ftp_chmod',
			'ftp_close','ftp_connect','ftp_delete','ftp_exec','ftp_fget','ftp_fput','ftp_get','ftp_get_option','ftp_login','ftp_mdtm','ftp_mkdir','ftp_nb_continue',
			'ftp_nb_fget','ftp_nb_fput','ftp_nb_get','ftp_nb_put','ftp_nlist','ftp_pasv','ftp_put','ftp_pwd','ftp_quit','ftp_raw','ftp_rawlist','ftp_rename',
			'ftp_rmdir','ftp_set_option','ftp_site','ftp_size','ftp_ssl_connect','ftp_systype','ftruncate','function_exists','func_get_arg','func_get_args',
			'func_num_args','fwrite','gd_info','getallheaders','getcwd','getdate','getenv','gethostbyaddr','gethostbyname','gethostbynamel','getimagesize',
			'getlastmod','getmxrr','getmygid','getmyinode','getmypid','getmyuid','getopt','getprotobyname','getprotobynumber','getrandmax','getrusage',
			'getservbyname','getservbyport','gettext','gettimeofday','gettype','get_browser','get_cfg_var','get_class','get_class_methods','get_class_vars',
			'get_current_user','get_declared_classes','get_defined_constants','get_defined_functions','get_defined_vars','get_extension_funcs','get_headers',
			'get_html_translation_table','get_included_files','get_include_path','get_loaded_extensions','get_magic_quotes_gpc','get_magic_quotes_runtime',
			'get_meta_tags','get_object_vars','get_parent_class','get_required_files','get_resource_type','glob','gmdate','gmmktime','gmp_abs','gmp_add','gmp_and',
			'gmp_clrbit','gmp_cmp','gmp_com','gmp_div','gmp_div_q','gmp_div_qr','gmp_div_r','gmp_divexact','gmp_fact','gmp_gcd','gmp_gcdext','gmp_hamdist',
			'gmp_init','gmp_intval','gmp_invert','gmp_jacobi','gmp_legendre','gmp_mod','gmp_mul','gmp_neg','gmp_nextprime','gmp_or','gmp_perfect_square',
			'gmp_popcount','gmp_pow','gmp_powm','gmp_prob_prime','gmp_random','gmp_scan0','gmp_scan1','gmp_setbit','gmp_sign','gmp_sqrt','gmp_sqrtrem',
			'gmp_strval','gmp_sub','gmp_xor','gmstrftime','gopher_parsedir','gregoriantojd','gzclose','gzcompress','gzdeflate','gzencode','gzeof','gzfile',
			'gzgetc','gzgets','gzgetss','gzinflate','gzopen','gzpassthru','gzputs','gzread','gzrewind','gzseek','gztell','gzuncompress','gzwrite','hash',
			'hash_algos','hash_file','hash_final','hash_hmac','hash_hmac_file','hash_init','hash_update','hash_update_file','hash_update_stream','header',
			'headers_list','headers_sent','hebrev','hebrevc','hexdec','highlight_file','highlight_string','html_doc','html_doc_file','html_entity_decode',
			'htmlentities','htmlspecialchars','htmlspecialchars_decode','http_build_cookie','http_build_query','http_build_str','http_build_url','http_cache_etag',
			'http_cache_last_modified','http_chunked_decode','http_date','http_deflate','http_get','http_get_request_body','http_get_request_body_stream',
			'http_get_request_headers','http_head','http_inflate','http_match_etag','http_match_modified','http_match_request_header','http_negotiate_charset',
			'http_negotiate_content_type','http_negotiate_language','http_parse_cookie','http_parse_headers','http_parse_message','http_parse_params',
			'http_persistent_handles_clean','http_persistent_handles_count','http_persistent_handles_ident','http_post_data','http_post_fields','http_put_data',
			'http_put_file','http_put_stream','http_redirect','http_request','http_request_body_encode','http_request_method_exists','http_request_method_name',
			'http_request_method_register','http_request_method_unregister','http_send_content_disposition','http_send_content_type','http_send_data',
			'http_send_file','http_send_last_modified','http_send_status','http_send_stream','http_support','http_throttle','hypot','i18n_convert',
			'i18n_discover_encoding','i18n_http_input','i18n_http_output','i18n_internal_encoding','i18n_ja_jp_hantozen','i18n_mime_header_decode',
			'i18n_mime_header_encode','ibase_add_user','ibase_affected_rows','ibase_backup','ibase_blob_add','ibase_blob_cancel','ibase_blob_close',
			'ibase_blob_create','ibase_blob_echo','ibase_blob_get','ibase_blob_import','ibase_blob_info','ibase_blob_open','ibase_close','ibase_commit',
			'ibase_commit_ret','ibase_connect','ibase_db_info','ibase_delete_user','ibase_drop_db','ibase_errcode','ibase_errmsg','ibase_execute',
			'ibase_fetch_assoc','ibase_fetch_object','ibase_fetch_row','ibase_field_info','ibase_free_event_handler','ibase_free_query','ibase_free_result',
			'ibase_gen_id','ibase_maintain_db','ibase_modify_user','ibase_name_result','ibase_num_fields','ibase_num_params','ibase_param_info','ibase_pconnect',
			'ibase_prepare','ibase_query','ibase_restore','ibase_rollback','ibase_rollback_ret','ibase_server_info','ibase_service_attach','ibase_service_detach',
			'ibase_set_event_handler','ibase_trans','ibase_wait_event','iconv','iconv_get_encoding','iconv_mime_decode','iconv_mime_decode_headers',
			'iconv_mime_encode','iconv_set_encoding','iconv_strlen','iconv_strpos','iconv_strrpos','iconv_substr','id3_get_frame_long_name','id3_get_frame_short_name',
			'id3_get_genre_id','id3_get_genre_list','id3_get_genre_name','id3_get_tag','id3_get_version','id3_remove_tag','id3_set_tag','idate','ignore_user_abort',
			'image_type_to_extension','image_type_to_mime_type','image2wbmp','imagealphablending','imageantialias','imagearc','imagechar','imagecharup',
			'imagecolorallocate','imagecolorallocatealpha','imagecolorat','imagecolorclosest','imagecolorclosestalpha','imagecolordeallocate','imagecolorexact',
			'imagecolorexactalpha','imagecolormatch','imagecolorresolve','imagecolorresolvealpha','imagecolorset','imagecolorsforindex','imagecolorstotal',
			'imagecolortransparent','imageconvolution','imagecopy','imagecopymerge','imagecopymergegray','imagecopyresampled','imagecopyresized','imagecreate',
			'imagecreatefromgd','imagecreatefromgd2','imagecreatefromgd2part','imagecreatefromgif','imagecreatefromjpeg','imagecreatefrompng','imagecreatefromstring',
			'imagecreatefromwbmp','imagecreatefromxbm','imagecreatetruecolor','imagedashedline','imagedestroy','imageellipse','imagefill','imagefilledarc',
			'imagefilledellipse','imagefilledpolygon','imagefilledrectangle','imagefilltoborder','imagefilter','imagefontheight','imagefontwidth','imageftbbox',
			'imagefttext','imagegammacorrect','imagegd','imagegd2','imagegif','imagegrabscreen','imagegrabwindow','imageinterlace','imageistruecolor','imagejpeg',
			'imagelayereffect','imageline','imageloadfont','imagepalettecopy','imagepng','imagepolygon','imagepsbbox','imagepsencodefont','imagepsextendfont',
			'imagepsfreefont','imagepsloadfont','imagepsslantfont','imagepstext','imagerectangle','imagerotate','imagesavealpha','imagesetbrush','imagesetpixel',
			'imagesetstyle','imagesetthickness','imagesettile','imagestring','imagestringup','imagesx','imagesy','imagetruecolortopalette','imagettfbbox',
			'imagettftext','imagetypes','imagewbmp','imagexbm','imap_8bit','imap_alerts','imap_append','imap_base64','imap_binary','imap_body','imap_bodystruct',
			'imap_check','imap_clearflag_full','imap_close','imap_create','imap_createmailbox','imap_delete','imap_deletemailbox','imap_errors','imap_expunge',
			'imap_fetch_overview','imap_fetchbody','imap_fetchheader','imap_fetchstructure','imap_fetchtext','imap_get_quota','imap_get_quotaroot','imap_getacl',
			'imap_getmailboxes','imap_getsubscribed','imap_header','imap_headerinfo','imap_headers','imap_last_error','imap_list','imap_listmailbox',
			'imap_listsubscribed','imap_lsub','imap_mail','imap_mail_compose','imap_mail_copy','imap_mail_move','imap_mailboxmsginfo','imap_mime_header_decode',
			'imap_msgno','imap_num_msg','imap_num_recent','imap_open','imap_ping','imap_qprint','imap_rename','imap_renamemailbox','imap_reopen',
			'imap_rfc822_parse_adrlist','imap_rfc822_parse_headers','imap_rfc822_write_address','imap_savebody','imap_scan','imap_scanmailbox','imap_search',
			'imap_set_quota','imap_setacl','imap_setflag_full','imap_sort','imap_status','imap_subscribe','imap_thread','imap_timeout','imap_uid','imap_undelete',
			'imap_unsubscribe','imap_utf7_decode','imap_utf7_encode','imap_utf8','implode','import_request_variables','in_array','ini_alter','ini_get','ini_get_all',
			'ini_restore','ini_set','intval','ip2long','iptcembed','iptcparse','isset','is_a','is_array','is_bool','is_callable','is_dir','is_double','is_executable',
			'is_file','is_finite','is_float','is_infinite','is_int','is_integer','is_link','is_long','is_nan','is_null','is_numeric','is_object','is_readable',
			'is_real','is_resource','is_scalar','is_soap_fault','is_string','is_subclass_of','is_uploaded_file','is_writable','is_writeable','iterator_apply',
			'iterator_count','iterator_to_array','java_last_exception_clear','java_last_exception_get','jddayofweek','jdmonthname','jdtofrench','jdtogregorian',
			'jdtojewish','jdtojulian','jdtounix','jewishtojd','join','jpeg2wbmp','json_decode','json_encode','juliantojd','key','key_exists','krsort','ksort',
			'lcg_value','ldap_add','ldap_bind','ldap_close','ldap_compare','ldap_connect','ldap_count_entries','ldap_delete','ldap_dn2ufn','ldap_err2str',
			'ldap_errno','ldap_error','ldap_explode_dn','ldap_first_attribute','ldap_first_entry','ldap_first_reference','ldap_free_result','ldap_get_attributes',
			'ldap_get_dn','ldap_get_entries','ldap_get_option','ldap_get_values','ldap_get_values_len','ldap_list','ldap_mod_add','ldap_mod_del','ldap_mod_replace',
			'ldap_modify','ldap_next_attribute','ldap_next_entry','ldap_next_reference','ldap_parse_reference','ldap_parse_result','ldap_read','ldap_rename',
			'ldap_search','ldap_set_option','ldap_sort','ldap_start_tls','ldap_unbind','levenshtein','libxml_clear_errors','libxml_get_errors',
			'libxml_get_last_error','libxml_set_streams_context','libxml_use_internal_errors','link','linkinfo','list','localeconv','localtime','log','log1p',
			'log10','long2ip','lstat','ltrim','lzf_compress','lzf_decompress','lzf_optimized_for','magic_quotes_runtime','mail','max','mbereg','mberegi',
			'mberegi_replace','mbereg_match','mbereg_replace','mbereg_search','mbereg_search_getpos','mbereg_search_getregs','mbereg_search_init',
			'mbereg_search_pos','mbereg_search_regs','mbereg_search_setpos','mbregex_encoding','mbsplit','mbstrcut','mbstrlen','mbstrpos','mbstrrpos',
			'mbsubstr','mb_check_encoding','mb_convert_case','mb_convert_encoding','mb_convert_kana','mb_convert_variables','mb_decode_mimeheader',
			'mb_decode_numericentity','mb_detect_encoding','mb_detect_order','mb_encode_mimeheader','mb_encode_numericentity','mb_ereg','mb_eregi',
			'mb_eregi_replace','mb_ereg_match','mb_ereg_replace','mb_ereg_search','mb_ereg_search_getpos','mb_ereg_search_getregs','mb_ereg_search_init',
			'mb_ereg_search_pos','mb_ereg_search_regs','mb_ereg_search_setpos','mb_get_info','mb_http_input','mb_http_output','mb_internal_encoding',
			'mb_language','mb_list_encodings','mb_output_handler','mb_parse_str','mb_preferred_mime_name','mb_regex_encoding','mb_regex_set_options',
			'mb_send_mail','mb_split','mb_strcut','mb_strimwidth','mb_stripos','mb_stristr','mb_strlen','mb_strpos','mb_strrchr','mb_strrichr','mb_strripos',
			'mb_strrpos','mb_strstr','mb_strtolower','mb_strtoupper','mb_strwidth','mb_substitute_character','mb_substr','mb_substr_count','mcrypt_cbc',
			'mcrypt_cfb','mcrypt_create_iv','mcrypt_decrypt','mcrypt_ecb','mcrypt_enc_get_algorithms_name','mcrypt_enc_get_block_size','mcrypt_enc_get_iv_size',
			'mcrypt_enc_get_key_size','mcrypt_enc_get_modes_name','mcrypt_enc_get_supported_key_sizes','mcrypt_enc_is_block_algorithm',
			'mcrypt_enc_is_block_algorithm_mode','mcrypt_enc_is_block_mode','mcrypt_enc_self_test','mcrypt_encrypt','mcrypt_generic','mcrypt_generic_deinit',
			'mcrypt_generic_end','mcrypt_generic_init','mcrypt_get_block_size','mcrypt_get_cipher_name','mcrypt_get_iv_size','mcrypt_get_key_size',
			'mcrypt_list_algorithms','mcrypt_list_modes','mcrypt_module_close','mcrypt_module_get_algo_block_size','mcrypt_module_get_algo_key_size',
			'mcrypt_module_get_supported_key_sizes','mcrypt_module_is_block_algorithm','mcrypt_module_is_block_algorithm_mode','mcrypt_module_is_block_mode',
			'mcrypt_module_open','mcrypt_module_self_test','mcrypt_ofb','md5','md5_file','mdecrypt_generic','memcache_add','memcache_add_server','memcache_close',
			'memcache_connect','memcache_debug','memcache_decrement','memcache_delete','memcache_flush','memcache_get','memcache_get_extended_stats',
			'memcache_get_server_status','memcache_get_stats','memcache_get_version','memcache_increment','memcache_pconnect','memcache_replace','memcache_set',
			'memcache_set_compress_threshold','memcache_set_server_params','memory_get_peak_usage','memory_get_usage','metaphone','mhash','mhash_count',
			'mhash_get_block_size','mhash_get_hash_name','mhash_keygen_s2k','method_exists','microtime','mime_content_type','min','ming_keypress',
			'ming_setcubicthreshold','ming_setscale','ming_useconstants','ming_useswfversion','mkdir','mktime','money_format','move_uploaded_file','msql',
			'msql_affected_rows','msql_close','msql_connect','msql_create_db','msql_createdb','msql_data_seek','msql_db_query','msql_dbname','msql_drop_db',
			'msql_dropdb','msql_error','msql_fetch_array','msql_fetch_field','msql_fetch_object','msql_fetch_row','msql_field_flags','msql_field_len',
			'msql_field_name','msql_field_seek','msql_field_table','msql_field_type','msql_fieldflags','msql_fieldlen','msql_fieldname','msql_fieldtable',
			'msql_fieldtype','msql_free_result','msql_freeresult','msql_list_dbs','msql_list_fields','msql_list_tables','msql_listdbs','msql_listfields',
			'msql_listtables','msql_num_fields','msql_num_rows','msql_numfields','msql_numrows','msql_pconnect','msql_query','msql_regcase','msql_result',
			'msql_select_db','msql_selectdb','msql_tablename','mssql_bind','mssql_close','mssql_connect','mssql_data_seek','mssql_execute','mssql_fetch_array',
			'mssql_fetch_assoc','mssql_fetch_batch','mssql_fetch_field','mssql_fetch_object','mssql_fetch_row','mssql_field_length','mssql_field_name',
			'mssql_field_seek','mssql_field_type','mssql_free_result','mssql_free_statement','mssql_get_last_message','mssql_guid_string','mssql_init',
			'mssql_min_error_severity','mssql_min_message_severity','mssql_next_result','mssql_num_fields','mssql_num_rows','mssql_pconnect','mssql_query',
			'mssql_result','mssql_rows_affected','mssql_select_db','mt_getrandmax','mt_rand','mt_srand','mysql','mysql_affected_rows','mysql_client_encoding',
			'mysql_close','mysql_connect','mysql_createdb','mysql_create_db','mysql_data_seek','mysql_dbname','mysql_db_name','mysql_db_query','mysql_dropdb',
			'mysql_drop_db','mysql_errno','mysql_error','mysql_escape_string','mysql_fetch_array','mysql_fetch_assoc','mysql_fetch_field','mysql_fetch_lengths',
			'mysql_fetch_object','mysql_fetch_row','mysql_fieldflags','mysql_fieldlen','mysql_fieldname','mysql_fieldtable','mysql_fieldtype','mysql_field_flags',
			'mysql_field_len','mysql_field_name','mysql_field_seek','mysql_field_table','mysql_field_type','mysql_freeresult','mysql_free_result',
			'mysql_get_client_info','mysql_get_host_info','mysql_get_proto_info','mysql_get_server_info','mysql_info','mysql_insert_id','mysql_listdbs',
			'mysql_listfields','mysql_listtables','mysql_list_dbs','mysql_list_fields','mysql_list_processes','mysql_list_tables','mysql_numfields','mysql_numrows',
			'mysql_num_fields','mysql_num_rows','mysql_pconnect','mysql_ping','mysql_query','mysql_real_escape_string','mysql_result','mysql_selectdb',
			'mysql_select_db','mysql_set_charset','mysql_stat','mysql_tablename','mysql_table_name','mysql_thread_id','mysql_unbuffered_query','mysqli_affected_rows',
			'mysqli_autocommit','mysqli_bind_param','mysqli_bind_result','mysqli_change_user','mysqli_character_set_name','mysqli_client_encoding','mysqli_close',
			'mysqli_commit','mysqli_connect','mysqli_connect_errno','mysqli_connect_error','mysqli_data_seek','mysqli_debug','mysqli_disable_reads_from_master',
			'mysqli_disable_rpl_parse','mysqli_dump_debug_info','mysqli_embedded_server_end','mysqli_embedded_server_start','mysqli_enable_reads_from_master',
			'mysqli_enable_rpl_parse','mysqli_errno','mysqli_error','mysqli_escape_string','mysqli_execute','mysqli_fetch','mysqli_fetch_array','mysqli_fetch_assoc',
			'mysqli_fetch_field','mysqli_fetch_field_direct','mysqli_fetch_fields','mysqli_fetch_lengths','mysqli_fetch_object','mysqli_fetch_row',
			'mysqli_field_count','mysqli_field_seek','mysqli_field_tell','mysqli_free_result','mysqli_get_charset','mysqli_get_client_info',
			'mysqli_get_client_version','mysqli_get_host_info','mysqli_get_metadata','mysqli_get_proto_info','mysqli_get_server_info','mysqli_get_server_version',
			'mysqli_get_warnings','mysqli_info','mysqli_init','mysqli_insert_id','mysqli_kill','mysqli_master_query','mysqli_more_results','mysqli_multi_query',
			'mysqli_next_result','mysqli_num_fields','mysqli_num_rows','mysqli_options','mysqli_param_count','mysqli_ping','mysqli_prepare','mysqli_query',
			'mysqli_real_connect','mysqli_real_escape_string','mysqli_real_query','mysqli_report','mysqli_rollback','mysqli_rpl_parse_enabled','mysqli_rpl_probe',
			'mysqli_rpl_query_type','mysqli_select_db','mysqli_send_long_data','mysqli_send_query','mysqli_set_charset','mysqli_set_local_infile_default',
			'mysqli_set_local_infile_handler','mysqli_set_opt','mysqli_slave_query','mysqli_sqlstate','mysqli_ssl_set','mysqli_stat','mysqli_stmt_affected_rows',
			'mysqli_stmt_attr_get','mysqli_stmt_attr_set','mysqli_stmt_bind_param','mysqli_stmt_bind_result','mysqli_stmt_close','mysqli_stmt_data_seek',
			'mysqli_stmt_errno','mysqli_stmt_error','mysqli_stmt_execute','mysqli_stmt_fetch','mysqli_stmt_field_count','mysqli_stmt_free_result',
			'mysqli_stmt_get_warnings','mysqli_stmt_init','mysqli_stmt_insert_id','mysqli_stmt_num_rows','mysqli_stmt_param_count','mysqli_stmt_prepare',
			'mysqli_stmt_reset','mysqli_stmt_result_metadata','mysqli_stmt_send_long_data','mysqli_stmt_sqlstate','mysqli_stmt_store_result','mysqli_store_result',
			'mysqli_thread_id','mysqli_thread_safe','mysqli_use_result','mysqli_warning_count','natcasesort','natsort','new_xmldoc','next','ngettext','nl2br',
			'nl_langinfo','ntuser_getdomaincontroller','ntuser_getusergroups','ntuser_getuserinfo','ntuser_getuserlist','number_format','ob_clean','ob_deflatehandler',
			'ob_end_clean','ob_end_flush','ob_etaghandler','ob_flush','ob_get_clean','ob_get_contents','ob_get_flush','ob_get_length','ob_get_level','ob_get_status',
			'ob_gzhandler','ob_iconv_handler','ob_implicit_flush','ob_inflatehandler','ob_list_handlers','ob_start','ob_tidyhandler','octdec','odbc_autocommit',
			'odbc_binmode','odbc_close','odbc_close_all','odbc_columnprivileges','odbc_columns','odbc_commit','odbc_connect','odbc_cursor','odbc_data_source',
			'odbc_do','odbc_error','odbc_errormsg','odbc_exec','odbc_execute','odbc_fetch_array','odbc_fetch_into','odbc_fetch_object','odbc_fetch_row',
			'odbc_field_len','odbc_field_name','odbc_field_num','odbc_field_precision','odbc_field_scale','odbc_field_type','odbc_foreignkeys','odbc_free_result',
			'odbc_gettypeinfo','odbc_longreadlen','odbc_next_result','odbc_num_fields','odbc_num_rows','odbc_pconnect','odbc_prepare','odbc_primarykeys',
			'odbc_procedurecolumns','odbc_procedures','odbc_result','odbc_result_all','odbc_rollback','odbc_setoption','odbc_specialcolumns','odbc_statistics',
			'odbc_tableprivileges','odbc_tables','opendir','openlog','openssl_csr_export','openssl_csr_export_to_file','openssl_csr_get_public_key',
			'openssl_csr_get_subject','openssl_csr_new','openssl_csr_sign','openssl_error_string','openssl_free_key','openssl_get_privatekey','openssl_get_publickey',
			'openssl_open','openssl_pkcs12_export','openssl_pkcs12_export_to_file','openssl_pkcs12_read','openssl_pkcs7_decrypt','openssl_pkcs7_encrypt',
			'openssl_pkcs7_sign','openssl_pkcs7_verify','openssl_pkey_export','openssl_pkey_export_to_file','openssl_pkey_free','openssl_pkey_get_details',
			'openssl_pkey_get_private','openssl_pkey_get_public','openssl_pkey_new','openssl_private_decrypt','openssl_private_encrypt','openssl_public_decrypt',
			'openssl_public_encrypt','openssl_seal','openssl_sign','openssl_verify','openssl_x509_checkpurpose','openssl_x509_check_private_key',
			'openssl_x509_export','openssl_x509_export_to_file','openssl_x509_free','openssl_x509_parse','openssl_x509_read','ord','output_add_rewrite_var',
			'output_reset_rewrite_vars','overload','outputdebugstring','pack','parse_ini_file','parse_str','parse_url','parsekit_compile_file',
			'parsekit_compile_string','parsekit_func_arginfo','parsekit_opcode_flags','parsekit_opcode_name','passthru','pathinfo','pclose','pdf_add_bookmark',
			'pdf_add_launchlink','pdf_add_locallink','pdf_add_nameddest','pdf_add_note','pdf_add_pdflink','pdf_add_thumbnail','pdf_add_weblink','pdf_arc',
			'pdf_arcn','pdf_attach_file','pdf_begin_font','pdf_begin_glyph','pdf_begin_page','pdf_begin_pattern','pdf_begin_template','pdf_circle','pdf_clip',
			'pdf_close','pdf_close_image','pdf_close_pdi','pdf_close_pdi_page','pdf_closepath','pdf_closepath_fill_stroke','pdf_closepath_stroke','pdf_concat',
			'pdf_continue_text','pdf_create_gstate','pdf_create_pvf','pdf_curveto','pdf_delete','pdf_delete_pvf','pdf_encoding_set_char','pdf_end_font',
			'pdf_end_glyph','pdf_end_page','pdf_end_pattern','pdf_end_template','pdf_endpath','pdf_fill','pdf_fill_imageblock','pdf_fill_pdfblock','pdf_fill_stroke',
			'pdf_fill_textblock','pdf_findfont','pdf_fit_image','pdf_fit_pdi_page','pdf_fit_textline','pdf_get_apiname','pdf_get_buffer','pdf_get_errmsg',
			'pdf_get_errnum','pdf_get_parameter','pdf_get_pdi_parameter','pdf_get_pdi_value','pdf_get_value','pdf_initgraphics','pdf_lineto','pdf_load_font',
			'pdf_load_iccprofile','pdf_load_image','pdf_makespotcolor','pdf_moveto','pdf_new','pdf_open_ccitt','pdf_open_file','pdf_open_image','pdf_open_image_file',
			'pdf_open_pdi','pdf_open_pdi_page','pdf_place_image','pdf_place_pdi_page','pdf_process_pdi','pdf_rect','pdf_restore','pdf_rotate','pdf_save','pdf_scale',
			'pdf_set_border_color','pdf_set_border_dash','pdf_set_border_style','pdf_set_gstate','pdf_set_info','pdf_set_parameter','pdf_set_text_pos',
			'pdf_set_value','pdf_setcolor','pdf_setdash','pdf_setdashpattern','pdf_setflat','pdf_setfont','pdf_setlinecap','pdf_setlinejoin','pdf_setlinewidth',
			'pdf_setmatrix','pdf_setmiterlimit','pdf_setpolydash','pdf_shading','pdf_shading_pattern','pdf_shfill','pdf_show','pdf_show_boxed','pdf_show_xy',
			'pdf_skew','pdf_stringwidth','pdf_stroke','pdf_translate','pdo_drivers','pfsockopen','pg_affected_rows','pg_cancel_query','pg_clientencoding',
			'pg_client_encoding','pg_close','pg_cmdtuples','pg_connect','pg_connection_busy','pg_connection_reset','pg_connection_status','pg_convert',
			'pg_copy_from','pg_copy_to','pg_dbname','pg_delete','pg_end_copy','pg_errormessage','pg_escape_bytea','pg_escape_string','pg_exec','pg_execute',
			'pg_fetch_all','pg_fetch_all_columns','pg_fetch_array','pg_fetch_assoc','pg_fetch_object','pg_fetch_result','pg_fetch_row','pg_fieldisnull',
			'pg_fieldname','pg_fieldnum','pg_fieldprtlen','pg_fieldsize','pg_fieldtype','pg_field_is_null','pg_field_name','pg_field_num','pg_field_prtlen',
			'pg_field_size','pg_field_table','pg_field_type','pg_field_type_oid','pg_free_result','pg_freeresult','pg_get_notify','pg_get_pid','pg_get_result',
			'pg_getlastoid','pg_host','pg_insert','pg_last_error','pg_last_notice','pg_last_oid','pg_loclose','pg_locreate','pg_loexport','pg_loimport',
			'pg_loopen','pg_loread','pg_loreadall','pg_lounlink','pg_lowrite','pg_lo_close','pg_lo_create','pg_lo_export','pg_lo_import','pg_lo_open','pg_lo_read',
			'pg_lo_read_all','pg_lo_seek','pg_lo_tell','pg_lo_unlink','pg_lo_write','pg_meta_data','pg_numfields','pg_numrows','pg_num_fields','pg_num_rows',
			'pg_options','pg_parameter_status','pg_pconnect','pg_ping','pg_port','pg_prepare','pg_put_line','pg_query','pg_query_params','pg_result',
			'pg_result_error','pg_result_error_field','pg_result_seek','pg_result_status','pg_select','pg_send_execute','pg_send_prepare','pg_send_query',
			'pg_send_query_params','pg_set_client_encoding','pg_set_error_verbosity','pg_setclientencoding','pg_trace','pg_transaction_status','pg_tty',
			'pg_unescape_bytea','pg_untrace','pg_update','pg_version','php_egg_logo_guid','php_ini_loaded_file','php_ini_scanned_files','php_logo_guid',
			'php_real_logo_guid','php_sapi_name','php_strip_whitespace','php_uname','phpcredits','phpdoc_xml_from_string','phpinfo','phpversion','pi',
			'png2wbmp','pop3_close','pop3_delete_message','pop3_get_account_size','pop3_get_message','pop3_get_message_count','pop3_get_message_header',
			'pop3_get_message_ids','pop3_get_message_size','pop3_get_message_sizes','pop3_open','pop3_undelete','popen','pos','posix_ctermid','posix_errno',
			'posix_getcwd','posix_getegid','posix_geteuid','posix_getgid','posix_getgrgid','posix_getgrnam','posix_getgroups','posix_getlogin','posix_getpgid',
			'posix_getpgrp','posix_getpid','posix_getppid','posix_getpwnam','posix_getpwuid','posix_getrlimit','posix_getsid','posix_getuid',
			'posix_get_last_error','posix_isatty','posix_kill','posix_mkfifo','posix_setegid','posix_seteuid','posix_setgid','posix_setpgid','posix_setsid',
			'posix_setuid','posix_strerror','posix_times','posix_ttyname','posix_uname','pow','preg_grep','preg_last_error','preg_match','preg_match_all',
			'preg_quote','preg_replace','preg_replace_callback','preg_split','prev','print_r','printf','proc_close','proc_get_status','proc_open',
			'proc_terminate','putenv','quoted_printable_decode','quotemeta','rad2deg','radius_acct_open','radius_add_server','radius_auth_open','radius_close',
			'radius_config','radius_create_request','radius_cvt_addr','radius_cvt_int','radius_cvt_string','radius_demangle','radius_demangle_mppe_key',
			'radius_get_attr','radius_get_vendor_attr','radius_put_addr','radius_put_attr','radius_put_int','radius_put_string','radius_put_vendor_addr',
			'radius_put_vendor_attr','radius_put_vendor_int','radius_put_vendor_string','radius_request_authenticator','radius_send_request','radius_server_secret',
			'radius_strerror','rand','range','rawurldecode','rawurlencode','read_exif_data','readdir','readfile','readgzfile','readlink','realpath','reg_close_key',
			'reg_create_key','reg_enum_key','reg_enum_value','reg_get_value','reg_open_key','reg_set_value','register_shutdown_function','register_tick_function',
			'rename','res_close','res_get','res_list','res_list_type','res_open','res_set','reset','restore_error_handler','restore_include_path','rewind',
			'rewinddir','rmdir','round','rsort','rtrim','runkit_class_adopt','runkit_class_emancipate','runkit_constant_add','runkit_constant_redefine',
			'runkit_constant_remove','runkit_default_property_add','runkit_function_add','runkit_function_copy','runkit_function_redefine','runkit_function_remove',
			'runkit_function_rename','runkit_import','runkit_lint','runkit_lint_file','runkit_method_add','runkit_method_copy','runkit_method_redefine',
			'runkit_method_remove','runkit_method_rename','runkit_object_id','runkit_return_value_used','runkit_sandbox_output_handler','runkit_superglobals',
			'runkit_zval_inspect','scandir','sem_acquire','sem_get','sem_release','sem_remove','serialize','session_cache_expire','session_cache_limiter',
			'session_commit','session_decode','session_destroy','session_encode','session_get_cookie_params','session_id','session_is_registered',
			'session_module_name','session_name','session_regenerate_id','session_register','session_save_path','session_set_cookie_params',
			'session_set_save_handler','session_start','session_unregister','session_unset','session_write_close','set_content','set_error_handler',
			'set_file_buffer','set_include_path','set_magic_quotes_runtime','set_socket_blocking','set_time_limit','setcookie','setlocale','setrawcookie',
			'settype','sha1','sha1_file','shell_exec','shmop_close','shmop_delete','shmop_open','shmop_read','shmop_size','shmop_write','shm_attach',
			'shm_detach','shm_get_var','shm_put_var','shm_remove','shm_remove_var','show_source','shuffle','similar_text','simplexml_import_dom',
			'simplexml_load_file','simplexml_load_string','sin','sinh','sizeof','sleep','smtp_close','smtp_cmd_data','smtp_cmd_mail','smtp_cmd_rcpt',
			'smtp_connect','snmp_get_quick_print','snmp_get_valueretrieval','snmp_read_mib','snmp_set_quick_print','snmp_set_valueretrieval','snmp2_get',
			'snmp2_getnext','snmp2_real_walk','snmp2_set','snmp2_walk','snmp3_get','snmp3_getnext','snmp3_real_walk','snmp3_set','snmp3_walk','snmpget',
			'snmpgetnext','snmprealwalk','snmpset','snmpwalk','snmpwalkoid','socket_accept','socket_bind','socket_clear_error','socket_close','socket_connect',
			'socket_create','socket_create_listen','socket_create_pair','socket_getopt','socket_getpeername','socket_getsockname','socket_get_option',
			'socket_get_status','socket_iovec_add','socket_iovec_alloc','socket_iovec_delete','socket_iovec_fetch','socket_iovec_free','socket_iovec_set',
			'socket_last_error','socket_listen','socket_read','socket_readv','socket_recv','socket_recvfrom','socket_recvmsg','socket_select','socket_send',
			'socket_sendmsg','socket_sendto','socket_setopt','socket_set_block','socket_set_blocking','socket_set_nonblock','socket_set_option',
			'socket_set_timeout','socket_shutdown','socket_strerror','socket_write','socket_writev','sort','soundex','spl_autoload','spl_autoload_call',
			'spl_autoload_extensions','spl_autoload_functions','spl_autoload_register','spl_autoload_unregister','spl_classes','spl_object_hash','split',
			'spliti','sprintf','sql_regcase','sqlite_array_query','sqlite_busy_timeout','sqlite_changes','sqlite_close','sqlite_column','sqlite_create_aggregate',
			'sqlite_create_function','sqlite_current','sqlite_error_string','sqlite_escape_string','sqlite_exec','sqlite_factory','sqlite_fetch_all',
			'sqlite_fetch_array','sqlite_fetch_column_types','sqlite_fetch_object','sqlite_fetch_single','sqlite_fetch_string','sqlite_field_name',
			'sqlite_has_more','sqlite_has_prev','sqlite_last_error','sqlite_last_insert_rowid','sqlite_libencoding','sqlite_libversion','sqlite_next',
			'sqlite_num_fields','sqlite_num_rows','sqlite_open','sqlite_popen','sqlite_prev','sqlite_query','sqlite_rewind','sqlite_seek','sqlite_single_query',
			'sqlite_udf_decode_binary','sqlite_udf_encode_binary','sqlite_unbuffered_query','sqlite_valid','sqrt','srand','sscanf','ssh2_auth_hostbased_file',
			'ssh2_auth_none','ssh2_auth_password','ssh2_auth_pubkey_file','ssh2_connect','ssh2_exec','ssh2_fetch_stream','ssh2_fingerprint','ssh2_forward_accept',
			'ssh2_forward_listen','ssh2_methods_negotiated','ssh2_poll','ssh2_publickey_add','ssh2_publickey_init','ssh2_publickey_list','ssh2_publickey_remove',
			'ssh2_scp_recv','ssh2_scp_send','ssh2_sftp','ssh2_sftp_lstat','ssh2_sftp_mkdir','ssh2_sftp_readlink','ssh2_sftp_realpath','ssh2_sftp_rename',
			'ssh2_sftp_rmdir','ssh2_sftp_stat','ssh2_sftp_symlink','ssh2_sftp_unlink','ssh2_shell','ssh2_tunnel','stat','stats_absolute_deviation','stats_cdf_beta',
			'stats_cdf_binomial','stats_cdf_cauchy','stats_cdf_chisquare','stats_cdf_exponential','stats_cdf_f','stats_cdf_gamma','stats_cdf_laplace',
			'stats_cdf_logistic','stats_cdf_negative_binomial','stats_cdf_noncentral_chisquare','stats_cdf_noncentral_f','stats_cdf_noncentral_t','stats_cdf_normal',
			'stats_cdf_poisson','stats_cdf_t','stats_cdf_uniform','stats_cdf_weibull','stats_covariance','stats_dens_beta','stats_dens_cauchy','stats_dens_chisquare',
			'stats_dens_exponential','stats_dens_f','stats_dens_gamma','stats_dens_laplace','stats_dens_logistic','stats_dens_normal','stats_dens_pmf_binomial',
			'stats_dens_pmf_hypergeometric','stats_dens_pmf_negative_binomial','stats_dens_pmf_poisson','stats_dens_t','stats_dens_uniform','stats_dens_weibull',
			'stats_harmonic_mean','stats_kurtosis','stats_rand_gen_beta','stats_rand_gen_chisquare','stats_rand_gen_exponential','stats_rand_gen_f',
			'stats_rand_gen_funiform','stats_rand_gen_gamma','stats_rand_gen_ipoisson','stats_rand_gen_iuniform','stats_rand_gen_noncenral_f',
			'stats_rand_gen_noncentral_chisquare','stats_rand_gen_noncentral_t','stats_rand_gen_normal','stats_rand_gen_t','stats_rand_getsd','stats_rand_ibinomial',
			'stats_rand_ibinomial_negative','stats_rand_ignlgi','stats_rand_phrase_to_seeds','stats_rand_ranf','stats_rand_setall','stats_skew',
			'stats_standard_deviation','stats_stat_binomial_coef','stats_stat_correlation','stats_stat_factorial','stats_stat_independent_t',
			'stats_stat_innerproduct','stats_stat_paired_t','stats_stat_percentile','stats_stat_powersum','stats_variance','strcasecmp','strchr','strcmp','strcoll',
			'strcspn','stream_bucket_append','stream_bucket_make_writeable','stream_bucket_new','stream_bucket_prepend','stream_context_create',
			'stream_context_get_default','stream_context_get_options','stream_context_set_default','stream_context_set_option','stream_context_set_params',
			'stream_copy_to_stream','stream_encoding','stream_filter_append','stream_filter_prepend','stream_filter_register','stream_filter_remove',
			'stream_get_contents','stream_get_filters','stream_get_line','stream_get_meta_data','stream_get_transports','stream_get_wrappers','stream_is_local',
			'stream_notification_callback','stream_register_wrapper','stream_resolve_include_path','stream_select','stream_set_blocking','stream_set_timeout',
			'stream_set_write_buffer','stream_socket_accept','stream_socket_client','stream_socket_enable_crypto','stream_socket_get_name','stream_socket_pair',
			'stream_socket_recvfrom','stream_socket_sendto','stream_socket_server','stream_socket_shutdown','stream_supports_lock','stream_wrapper_register',
			'stream_wrapper_restore','stream_wrapper_unregister','strftime','stripcslashes','stripos','stripslashes','strip_tags','stristr','strlen','strnatcasecmp',
			'strnatcmp','strpbrk','strncasecmp','strncmp','strpos','strrchr','strrev','strripos','strrpos','strspn','strstr','strtok','strtolower','strtotime',
			'strtoupper','strtr','strval','str_ireplace','str_pad','str_repeat','str_replace','str_rot13','str_split','str_shuffle','str_word_count','substr',
			'substr_compare','substr_count','substr_replace','svn_add','svn_auth_get_parameter','svn_auth_set_parameter','svn_cat','svn_checkout','svn_cleanup',
			'svn_client_version','svn_commit','svn_diff','svn_export','svn_fs_abort_txn','svn_fs_apply_text','svn_fs_begin_txn2','svn_fs_change_node_prop',
			'svn_fs_check_path','svn_fs_contents_changed','svn_fs_copy','svn_fs_delete','svn_fs_dir_entries','svn_fs_file_contents','svn_fs_file_length',
			'svn_fs_is_dir','svn_fs_is_file','svn_fs_make_dir','svn_fs_make_file','svn_fs_node_created_rev','svn_fs_node_prop','svn_fs_props_changed',
			'svn_fs_revision_prop','svn_fs_revision_root','svn_fs_txn_root','svn_fs_youngest_rev','svn_import','svn_info','svn_log','svn_ls','svn_repos_create',
			'svn_repos_fs','svn_repos_fs_begin_txn_for_commit','svn_repos_fs_commit_txn','svn_repos_hotcopy','svn_repos_open','svn_repos_recover','svn_status',
			'svn_update','symlink','sys_get_temp_dir','syslog','system','tan','tanh','tempnam','textdomain','thread_get','thread_include','thread_lock',
			'thread_lock_try','thread_mutex_destroy','thread_mutex_init','thread_set','thread_start','thread_unlock','tidy_access_count','tidy_clean_repair',
			'tidy_config_count','tidy_diagnose','tidy_error_count','tidy_get_body','tidy_get_config','tidy_get_error_buffer','tidy_get_head','tidy_get_html',
			'tidy_get_html_ver','tidy_get_output','tidy_get_release','tidy_get_root','tidy_get_status','tidy_getopt','tidy_is_xhtml','tidy_is_xml',
			'tidy_parse_file','tidy_parse_string','tidy_repair_file','tidy_repair_string','tidy_warning_count','time','timezone_abbreviations_list',
			'timezone_identifiers_list','timezone_name_from_abbr','timezone_name_get','timezone_offset_get','timezone_open','timezone_transitions_get',
			'tmpfile','token_get_all','token_name','touch','trigger_error','transliterate','transliterate_filters_get','trim','uasort','ucfirst','ucwords',
			'uksort','umask','uniqid','unixtojd','unlink','unpack','unregister_tick_function','unserialize','unset','urldecode','urlencode','user_error',
			'use_soap_error_handler','usleep','usort','utf8_decode','utf8_encode','var_dump','var_export','variant_abs','variant_add','variant_and','variant_cast',
			'variant_cat','variant_cmp','variant_date_from_timestamp','variant_date_to_timestamp','variant_div','variant_eqv','variant_fix','variant_get_type',
			'variant_idiv','variant_imp','variant_int','variant_mod','variant_mul','variant_neg','variant_not','variant_or','variant_pow','variant_round',
			'variant_set','variant_set_type','variant_sub','variant_xor','version_compare','virtual','vfprintf','vprintf','vsprintf','wddx_add_vars',
			'wddx_deserialize','wddx_packet_end','wddx_packet_start','wddx_serialize_value','wddx_serialize_vars','win_beep','win_browse_file','win_browse_folder',
			'win_create_link','win_message_box','win_play_wav','win_shell_execute','win32_create_service','win32_delete_service','win32_get_last_control_message',
			'win32_ps_list_procs','win32_ps_stat_mem','win32_ps_stat_proc','win32_query_service_status','win32_scheduler_delete_task','win32_scheduler_enum_tasks',
			'win32_scheduler_get_task_info','win32_scheduler_run','win32_scheduler_set_task_info','win32_set_service_status','win32_start_service',
			'win32_start_service_ctrl_dispatcher','win32_stop_service','wordwrap','xml_error_string','xml_get_current_byte_index','xml_get_current_column_number',
			'xml_get_current_line_number','xml_get_error_code','xml_parse','xml_parser_create','xml_parser_create_ns','xml_parser_free','xml_parser_get_option',
			'xml_parser_set_option','xml_parse_into_struct','xml_set_character_data_handler','xml_set_default_handler','xml_set_element_handler',
			'xml_set_end_namespace_decl_handler','xml_set_external_entity_ref_handler','xml_set_notation_decl_handler','xml_set_object',
			'xml_set_processing_instruction_handler','xml_set_start_namespace_decl_handler','xml_set_unparsed_entity_decl_handler','xmldoc','xmldocfile',
			'xmlrpc_decode','xmlrpc_decode_request','xmlrpc_encode','xmlrpc_encode_request','xmlrpc_get_type','xmlrpc_is_fault','xmlrpc_parse_method_descriptions',
			'xmlrpc_server_add_introspection_data','xmlrpc_server_call_method','xmlrpc_server_create','xmlrpc_server_destroy',
			'xmlrpc_server_register_introspection_callback','xmlrpc_server_register_method','xmlrpc_set_type','xmltree','xmlwriter_end_attribute',
			'xmlwriter_end_cdata','xmlwriter_end_comment','xmlwriter_end_document','xmlwriter_end_dtd','xmlwriter_end_dtd_attlist','xmlwriter_end_dtd_element',
			'xmlwriter_end_dtd_entity','xmlwriter_end_element','xmlwriter_end_pi','xmlwriter_flush','xmlwriter_full_end_element','xmlwriter_open_memory',
			'xmlwriter_open_uri','xmlwriter_output_memory','xmlwriter_set_indent','xmlwriter_set_indent_string','xmlwriter_start_attribute',
			'xmlwriter_start_attribute_ns','xmlwriter_start_cdata','xmlwriter_start_comment','xmlwriter_start_document','xmlwriter_start_dtd',
			'xmlwriter_start_dtd_attlist','xmlwriter_start_dtd_element','xmlwriter_start_dtd_entity','xmlwriter_start_element','xmlwriter_start_element_ns',
			'xmlwriter_start_pi','xmlwriter_text','xmlwriter_write_attribute','xmlwriter_write_attribute_ns','xmlwriter_write_cdata','xmlwriter_write_comment',
			'xmlwriter_write_dtd','xmlwriter_write_dtd_attlist','xmlwriter_write_dtd_element','xmlwriter_write_dtd_entity','xmlwriter_write_element',
			'xmlwriter_write_element_ns','xmlwriter_write_pi','xmlwriter_write_raw','xpath_eval','xpath_eval_expression','xpath_new_context','xpath_register_ns',
			'xpath_register_ns_auto','xptr_eval','xptr_new_context','yp_all','yp_cat','yp_errno','yp_err_string','yp_first','yp_get_default_domain','yp_master',
			'yp_match','yp_next','yp_order','zend_current_obfuscation_level','zend_get_cfg_var','zend_get_id','zend_loader_current_file','zend_loader_enabled',
			'zend_loader_file_encoded','zend_loader_file_licensed','zend_loader_install_license','zend_loader_version','zend_logo_guid','zend_match_hostmasks',
			'zend_obfuscate_class_name','zend_obfuscate_function_name','zend_optimizer_version','zend_runtime_obfuscate','zend_version','zip_close',
			'zip_entry_close','zip_entry_compressedsize','zip_entry_compressionmethod','zip_entry_filesize','zip_entry_name','zip_entry_open','zip_entry_read',
			'zip_open','zip_read','zlib_get_coding_type',
			
			'DEFAULT_INCLUDE_PATH', 'DIRECTORY_SEPARATOR', 'E_ALL',
			'E_COMPILE_ERROR', 'E_COMPILE_WARNING', 'E_CORE_ERROR',
			'E_CORE_WARNING', 'E_ERROR', 'E_NOTICE', 'E_PARSE', 'E_STRICT',
			'E_USER_ERROR', 'E_USER_NOTICE', 'E_USER_WARNING', 'E_WARNING',
			'ENT_COMPAT','ENT_QUOTES','ENT_NOQUOTES',
			'false', 'null', 'PEAR_EXTENSION_DIR', 'PEAR_INSTALL_DIR',
			'PHP_BINDIR', 'PHP_CONFIG_FILE_PATH', 'PHP_DATADIR',
			'PHP_EXTENSION_DIR', 'PHP_LIBDIR',
			'PHP_LOCALSTATEDIR', 'PHP_OS',
			'PHP_OUTPUT_HANDLER_CONT', 'PHP_OUTPUT_HANDLER_END',
			'PHP_OUTPUT_HANDLER_START', 'PHP_SYSCONFDIR',
			'PHP_VERSION', 'true', '__CLASS__', '__FILE__', '__FUNCTION__',
			'__LINE__', '__METHOD__',
			
			'PHP_MAJOR_VERSION', 'PHP_MINOR_VERSION', 'PHP_RELEASE_VERSION', 'PHP_VERSION_ID', 'PHP_EXTRA_VERSION',
			'PHP_ZTS', 'PHP_DEBUG', 'PHP_MAXPATHLEN', 'PHP_SAPI', 'PHP_EOL', 'PHP_INT_MAX', 'PHP_INT_MIN',
			'PHP_INT_SIZE', 'PHP_FLOAT_DIG', 'PHP_FLOAT_EPSILON', 'PHP_FLOAT_MIN', 'PHP_FLOAT_MAX', 'DEFAULT_INCLUDE_PATH',
			'PEAR_INSTALL_DIR', 'PEAR_EXTENSION_DIR', 'PHP_PREFIX', 'PHP_BINARY', 'PHP_MANDIR',
			'PHP_CONFIG_FILE_SCAN_DIR',
			'PHP_SHLIB_SUFFIX', 'PHP_FD_SETSIZE',
			'__COMPILER_HALT_OFFSET__', 'PATHINFO_EXTENSION',
			'<?php','?>','<?=','<?','require','require_once','include','include_once',
			
			'as','break','case','continue','default','do','else','elseif',
			'endfor','endforeach','endif','endswitch','endwhile','for',
			'foreach','if',
			'return','switch','throw','while','exit','die',
			
			'&new','var','new','private','protected','public','self','const','static','__construct',
			'echo','print', 'float', 'int', 'string', 'number', 'boolean',
			'function','global','use','abstract','class','declare','extends','interface','namespace'
		];
	}
}
