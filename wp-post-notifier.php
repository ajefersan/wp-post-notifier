<?php
/*
Plugin Name: Notificações por e-mail
Plugin URI: https://github.com/diegoscosta/wp-post-notifier
Description: Plugin para enviar notificações quando um novo artigo for publicado. 
Version: 2.0
Author: Diego Costa
Author URI: http://www.diegocosta.com.br
*/

class DC_WP_Post_Notifier
{
	private $frequency;
	private $emails;
	private $categories;
	private $message;

	public function __construct() 
	{
		add_action('admin_menu', array($this, 'menu'));
		$this->frequency 	= get_option("wpn_frequency");
		$this->emails 		= get_option("wpn_emails");
		$this->categories 	= get_option("wpn_categories");
		$this->message 		= get_option("wpn_message");
		$this->push($this->frequency);
	}

	public function push($frequency)
	{
		if($frequency == 0)
		{
			$posts_status = array('new_to_publish', 'draft_to_publish', 'pending_to_publish', 'auto-draft_to_publish');
			foreach($posts_status as $key) { add_action($key, array($this, 'post_notification')); }
		} 
		else 
		{
			add_action('publish_post', array($this, 'post_notification'));
		}
	}

	public function post_notification($post_id) 
	{
	   $post = get_post($post_id);
	   $cats = wp_get_post_categories((($this->frequency == 0) ? $post_id->ID : $post_id));

	   if($this->validate_categories($cats)) 
	   {
	   		add_filter( 'wp_mail_content_type', array($this,'set_html_content_type') );
			wp_mail($this->validate_emails($this->emails), get_option("blogname") .': '. $post->post_title, $this->validate_message($post), 'From: '.get_option("blogname").' <'.get_option("admin_email").'>' . "\r\n");
			remove_filter( 'wp_mail_content_type', array($this,'set_html_content_type') );
	   }
	   
	}

	public function menu() 
	{
		add_submenu_page('index.php', 'Notificações', 'Notificações', 'manage_options', 'wp-post-notifier', array($this, 'painel') );
	}

	public function painel() 
	{
		$wpts_campos[] = array('name' => 'wpn_emails', 'type' => 'textarea', 'label' => 'Contas de E-mail', 'description' => 'Separe os e-mails por virgula.');
		$wpts_campos[] = array('name' => 'wpn_frequency', 'type' => 'select', 'label' => 'Quando notificar', 'options' => array('Novo Post', 'Nova Atualização'));
		$wpts_campos[] = array('name' => 'wpn_categories', 'type' => 'text', 'label' => 'Filtro de categorias', 'description' => 'Deixe em branco para todas as categorias ou informe os ID\'s das categorias desejadas separadas por virgula.');
		$wpts_campos[] = array('name' => 'wpn_message', 'type' => 'textarea', 'label' => 'Modelo de Mensagem', 'description' => 'Use as tags: {author} {date} {link} {title} para personalizar sua mensagem.');
		$this->front_page($wpts_campos, array('title' => 'Notificações por e-mail', 'btn_save' => 'Salvar alterações', 'description' => ''));
	}

	public function validate_categories($post) 
	{
		if(!empty($this->categories)) 
		{
            $categories = explode(',', str_replace(" ", '', $this->categories));
            foreach ($categories as $catkey => $catvalue) { if(!is_int($catvalue)) unset($categories[$catkey]); }
            foreach($post as $key) { if(in_array($key, $categories)) return true; }
        } 
        else 
        {
            return true;
        }
	}

	public function validate_emails($emails)
	{
		if(empty($emails)) 
		{ 
			$emails = get_option("admin_email"); 
		} 
		else  
		{
			$emails = explode(',', str_replace(" ", '', get_option("wpn_emails"))); 
			foreach ($emails as $key => $value) { if (!filter_var($value, FILTER_VALIDATE_EMAIL)) { unset($emails[$key]); } }
			return $emails;
		}
	}


	public function validate_message($post) 
	{
		$author = get_userdata($post->post_author);
		return str_replace(array('{date}', '{author}', '{title}', '{link}'), array($post->post_date, $author->first_name, $post->post_title, '<a href="'. get_permalink( $post_id ).'" taget="_black">'. get_permalink( $post_id ).'</a>'), '<pre>'.((!empty($this->message)) ? $this->message : "{title}<br>{link}").'</pre>');
	}

	public function set_html_content_type() 
	{
		return 'text/html';
	}

	public function front_page($opcoes, $info)
	{
		echo '<div class="wrap"><h2>'. $info['title'] .'</h2><form method="post" action="options.php"><table class="form-table">'; wp_nonce_field('update-options');
		echo '<p>'. $info['description'] .'</p>';

		foreach($opcoes as $key) 
		{
			echo '<tr valign="top"><th scope="row"><label for="'.$key['name'].'">'.$key['label'].'</label></th><td>';

	        	if($key['type'] == 'textarea') 
	        	{
	        		echo '<textarea name="'.$key['name'].'" id="'.$key['name'].'" class="large-text" style="height:150px">'.get_option($key['name']).'</textarea>';
	        	}

	        	elseif($key['type'] == 'select') 
	        	{
	        		echo '<select name="'.$key['name'].'" id="'.$key['name'].'">'; 
	        			foreach($key['options'] as $id => $value) { echo '<option value="'. $id .'"'. ((get_option($key['name']) == $id) ? ' selected' : '') .'>'. $value .'</option>'; }
	        		echo '</select>';
	        	} 
	        	else 
	        	{
	        		echo '<input type="text" name="'.$key['name'].'" id="'.$key['name'].'" size="45" value="'.get_option($key['name']).'" class="regular-text"/>';
	        	}

	        	if(!empty($key['description'])) 
	        	{ 
	        		echo '<p class="description">'.$key['description'].'</p>';
	        	}
			echo '</td></tr>';

			 $page_options_strings .= $key['name'].',';
		}
		 echo '</table>
	            <input type="hidden" name="action" value="update" />
	            <input type="hidden" name="page_options" value="'.$page_options_strings.'" />
	            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="'. $info['btn_save'] .'"  /></p>
	        </form>
	    </div>';
	}
}
$DC_WP_Post_Notifier = new DC_WP_Post_Notifier();