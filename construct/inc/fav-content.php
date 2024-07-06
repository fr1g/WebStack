<?php  
/*
 * @Theme Name:WebStack
 * @Theme URI:https://www.iotheme.cn/
 * @Author: iowen
 * @Author URI: https://www.iowen.cn/
 * @Date: 2020-02-22 21:26:05
 * @LastEditors: iowen
 * @LastEditTime: 2023-02-20 20:55:06
 * @FilePath: \WebStack\inc\fav-content.php
 * @Description: 
 */
$xtmp;
if ( ! defined( 'ABSPATH' ) ) { exit; }
function fav_con($mid) { ?>
        <h4 class="text-gray" style="display: inline-block;"><i class="icon-io-tag" style="margin-right: 27px;" id="term-<?php echo $mid->term_id; ?>"></i><?php echo $mid->name; ?></h4>
        <?php 
        $site_n           = io_get_option('site_n');
        $category_count   = $mid->category_count;
        $count            = $site_n;
        if($site_n == 0)  $count = min(get_option('posts_per_page'),$category_count);
        if($site_n >= 0 && $count < $category_count){
          $link = esc_url( get_term_link( $mid, 'res_category' ) );
          echo "<a class='btn-move' href='$link'>more+</a>";
        }
        ?>
        <div class="row">
        <?php   
        #single inside 2nd class
          //定义$post为全局变量，这样之后的输出就不会是同一篇文章了
          global $post;
          //下方的posts_per_page设置最为重要
          $args = array(
            'post_type'           => 'sites',        //自定义文章类型，这里为sites
            'ignore_sticky_posts' => 1,              //忽略置顶文章
            'posts_per_page'      => $site_n,        //显示的文章数量
            'meta_key'            => '_sites_order',
            'orderby'             => array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' ),
            'tax_query'           => array(
                array(
                    'taxonomy' => 'favorites',       //分类法名称
                    'field'    => 'id',              //根据分类法条款的什么字段查询，这里设置为ID
                    'terms'    => $mid->term_id,     //分类法条款，输入分类的ID，多个ID使用数组：array(1,2)
                )
            ),
          );
          $myposts = new WP_Query( $args );
          if(!$myposts->have_posts()): ?>
          <div class="col-lg-12">
            <div class="nothing"><?php _e('没有内容','i_theme') ?></div>
          </div>
          <?php
          elseif ($myposts->have_posts()): 
            while ($myposts->have_posts()): # this is a for loop in fact?>


              
              <?php
                # here start to prepare info for cards
                $myposts->the_post(); 
                $link_url = get_post_meta($post->ID, '_sites_link', true); 
                $default_ico = get_theme_file_uri('/images/favicon.png');
                # if visible
                  if(current_user_can('level_10') || get_post_meta($post->ID, '_visible', true)==""):
                    # during this procedure, get the sub classes rid off inside tmp[class]: key => [items]
                    # echo json_encode($post).'<br>';
                    $_tmpn = $post->post_title;
                    $_tmp = strstr($_tmpn, '@@');
                    if($_tmp){
                      $_tmpclass = trim( str_replace('@@', '', $_tmp) );
                      $GLOBALS['xtmp'][$_tmpclass][$_tmpn] = $GLOBALS['post'];
                    }else {
                      $GLOBALS['xtmp']['default'][$_tmpn] = $GLOBALS['post'];
                    }

                  endif; endwhile; 
                  
              ?>

              <?php 
                foreach($GLOBALS['xtmp']['default'] as $i_post){ 
                  $GLOBALS['post'] = $i_post;
              ?>
                
                      <div class="xe-card <?php echo io_get_option('columns') ?> <?php echo get_post_meta($post->ID, '_wechat_qr', true)? 'wechat':''?>">
                        <!-- here start to insert cards -->
                        <?php 

                        ?>
                        <?php include( get_theme_file_path() .'/templates/site-card.php' ); ?>
                      </div>

                <?php
                }
              ?>
              <?php 
                foreach($GLOBALS['xtmp'] as $k => $v){
                  // $__proccs;
                  if($k != 'default'){ 
                    $__xclass = hash('md5', $k);
                    // echo $__xclass . '    xx ' . $k;
                    ?>
                    <div class="panel-group mt-3 col-xs-12" id="accordion" role="tablist" aria-multiselectable="true">
                      <div class="panel panel-default">
                        <div class="panel-heading fix" role="tab" id="heading<?php echo $__xclass; ?>">
                          <h4 class="panel-title">
                            <a role="button" data-toggle="collapse" class="useDefaultAnchor" data-parent="#accordion" href="#collapse<?php echo $__xclass; ?>" aria-expanded="false" aria-controls="collapse<?php echo $__xclass; ?>">
                              <i class="fa fa-bars"></i> <?php echo $k; ?>
                            </a>
                          </h4>
                        </div>
                        <div id="collapse<?php echo $__xclass; ?>" class="panel-collapse collapse d-grid fix" style="" role="tabpanel" aria-labelledby="heading<?php echo $__xclass; ?>">
                          <div class="panel-body fix row col-xs-12">
                      <?php
                        foreach($v as $i_post)      {
                          $GLOBALS['post'] = $i_post;
                          $__xid = $GLOBALS['post']->ID;
                      ?>

                    
                            <div class="xe-card <?php echo io_get_option('columns') ?> <?php echo get_post_meta($__xid, '_wechat_qr', true)? 'wechat':''?>">
                              <!-- here start to insert cards -->
                              <?php 

                              ?>
                              <?php include( get_theme_file_path() .'/templates/site-card.php' ); ?>
                            </div>


                      <?php  
                        }
                        ?>
                            <div></div>
                            <br>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php
                  }
                }
              // if is default
              
              
              ?>
                
  
              <?php  
                  endif;
                  wp_reset_postdata(); ?>
        </div>   
        <br /> 
<?php } ?>