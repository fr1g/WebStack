<?php 
global $wpdb;
    global $_pages, $_eachPageRess, $_filter;
    $_rqi = str_replace($_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']) . 'page=s-man&';
    $_reqParamSet = explode('&', $_SERVER["QUERY_STRING"]);
    $_reqParamParsed = [];
    foreach($_reqParamSet as $_reqp){
        $__tmp = explode('=', $_reqp);
        $_reqParamParsed[$__tmp[0]] = $__tmp[1] ?? 'empty';
    }
    $_forPage = (int)($_reqParamParsed['forpage'] ?? '1'); // 当前页码
    $_operation = $_reqParamParsed['opr'] ?? '-1tonol'; // 操作 （<页码>to<状态：pending | deny | accept>fr<? 状态：pending | deny | accept>） 在操作完成后应该考虑清空地址栏的此段参数
    $_filter = $_reqParamParsed['filtering'] ?? 'pending'; // 过滤，仅显示三个状态下的一个状态

    $__opTarget = (int)explode('to', $_operation)[0];
    $__opDetail = explode('to', $_operation)[1];
    $__opFrom = 'empty';
    $__opTo = 'empty';
    if(strstr($_operation, 'fr') !== false) {
        $__opTo = explode('fr', $__opDetail)[0];
        $__opFrom = explode('fr', $__opDetail)[1];
    }

    $_classes = [];
    foreach(($wpdb -> get_results("select term_id, slug, name from wp_terms where slug like '%-type' and 1=1", 'ARRAY_A'))  as $x){
        $_classes[$x['slug']] = $x['name'].' ('.$x['term_id'].')';
    }

    // 通过：转移到accept，然后录入到对应表 
    // 拒绝：转移到deny，如果先前为accept，如果对应表中存在这个项目（名称和网址完全匹配）则将删除该项目。
    // 撤销：恢复到pending状态，如果先前为accept，如果对应表中存在这个项目（名称和网址完全匹配）则将删除该项目。
    // 在某个状态下，将会仅使另外两个状态有效可操作。

    if($__opFrom == $__opTo && $__opFrom != 'empty') echo 'refused operation'; // 
    elseif($__opDetail == 'killall'){ // remove all DENied
        $wpdb->query('delete from wp_ex_submissions where stat = \'deny\'', ARRAY_A);
    }
    elseif($__opFrom != 'empty' && $__opTo != 'empty' && $__opTo != 'accept'){ // to PENDING and to DENY
        // $___result = ($wpdb -> get_results('select * from wp_ex_submissions where id = '. $__opTarget))[0];
        // check if already added into the place, get the target id
        $___prepare = ($wpdb -> get_results('select * from wp_ex_submissions where id = '. $__opTarget, ARRAY_A))[0];
        $wpdb -> query('update wp_ex_submissions set next = \'unset\', stat = \'' . $__opTo . '\' where id = '. $__opTarget);
        if($___prepare['next'] != 'unset'){
            wp_delete_post((int)$___prepare['next']);
        }

    }elseif($__opTo == 'accept'){ // to ACCEPT
        $___prepare = ($wpdb -> get_results('select * from wp_ex_submissions where id = '. $__opTarget, ARRAY_A))[0];
        // echo json_encode($___prepare);
        $__np = [
            'post_title' => explode('@@', $___prepare['name'])[0].'@@未分类的投稿项',
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'sites'
        ];
        $__npid = wp_insert_post($__np);
        if($__npid){
            $____tocat = (int)(explode('(', explode(')', $_classes[ $___prepare['type']] ?? '')[0])[1] ?? -1);
            if($____tocat == -1) echo '<br>失败：有不存在的分类' . $___prepare['type'] . '，将会尝试直接添加到无分类中。';
            else wp_set_object_terms($__npid, [$____tocat], 'favorites', false);
            add_post_meta($__npid, '_site_link', $___prepare['link']);
            add_post_meta($__npid, '_site_sescribe', $___prepare['easy']);
            $wpdb -> query('update wp_ex_submissions set next = \''.$__npid.'\', stat = \'' . $__opTo . '\' where id = '. $__opTarget);
        }

    }



    $_eachPageRess = 7; // 分页时，单页的条数
    $_totalCounts = $wpdb -> get_var($wpdb -> prepare('select count(*) from wp_ex_submissions where stat = \'' . $GLOBALS['_filter'] . '\''));
    $_pages = (((int)((int)$_totalCounts / $_eachPageRess)) + ((int)$_totalCounts % $_eachPageRess == 0 ? 0 : 1));

    function queryPage($to){
        if($to > $GLOBALS['_pages'] || $to <= 0) return 'refuse';
        else return 'select * from wp_ex_submissions where stat = \'' . $GLOBALS['_filter'] . '\' and 1=1 limit ' . (($to - 1) * $GLOBALS['_eachPageRess']) . ', ' . ($GLOBALS['_eachPageRess']);
    }

    $_dataSet = $wpdb -> get_results($wpdb -> prepare(queryPage($_forPage)), ARRAY_A);
?>
<div style="z-index: -1 !important">
    
    <h3>管理投稿</h3>
    <h5>当前页面：<?php echo $_forPage; ?>, 总共有 <?php echo $_totalCounts; ?>条记录，合计 <?php echo $_pages; ?>页</h5>
    <p>
        由于可能存在虚报更新状态的情况，所以需要亲自确认更新频率然后手动更新条目。<br>
        PHP写得太难受了，所以这丑陋UI就先忍忍罢！(我甚至也不打算写响应式了，这里是一点css框架都用不了) <br>
        <!-- <?php echo $_rqi; ?> -->
    </p>
    <style> /* from: <?php echo (get_template_directory() . '/api/manage.css.php'); ?> */ <?php include(get_template_directory() . '/api/manage.css') ?> </style>
    <script>
        let CURR_PAGE = <?php echo $_forPage; ?>,
            TOTAL_PAGES = <?php echo $_pages; ?>,
            EACH_PAGE = <?php echo $_eachPageRess; ?>;

        if(window.location.href.includes('opr=')) window.location.replace(`${window.location.href}`.replace(`<?php echo 'opr=' . ($_reqParamParsed['opr'] ?? '-1tonol'); ?>`, ''));
        

    </script>
    <table class="table">
        <thead>
            <tr class="thead">
                <td title="如果存在括号，括号内的内容即为对应的实际发布ID">编号</td>
                <td>名称</td>
                <td>链接 & 简介</td>
                <td>分类</td>
                <td>详细信息（点击来通过alert查看）</td>
                <td>状态</td>
                <td>操作</td>
            </tr>
        </thead>
        <tbody>
            <?php 
                // echo json_encode($_classes);
                if(count($_dataSet) != 0)
                foreach($_dataSet as $__s){
            ?>
                <tr>
                    <td class="center"><?php echo ' ' . $__s['id'] . ' ' . ($__s['next'] == 'unset' ? '' : (' (' . $__s['next'] . ')')); ?></td>
                    <td><?php echo $__s['name']; ?></td>
                    <td>
                        <?php echo $__s['easy']; ?><br>
                        <a href="<?php echo $__s['link']; ?>" target="_blank"><?php echo $__s['link']; ?></a>
                    </td>
                    <td><?php echo $_classes[$__s['type']] ?? ($__s['type'] . '（不复存在的分类）'); ?></td>
                    <td class="detailAlert"><?php echo $__s['dscr']; ?></td>
                    <td class="stat-line"><?php echo $__s['stat']; ?></td>
                    <td class="op-line">
                        <span class="<?php if($_filter == 'accept') echo 'disabled'; ?>">
                            <a href="<?php echo $_rqi.'forpage='.$_forPage.'&opr='.$__s['id'].'toacceptfr'.$__s['stat'].'&filtering='.$_filter;?>">通过</a>
                        </span> 
                        | 
                        <span class="<?php if($_filter == 'deny') echo 'disabled'; ?>">
                            <a href="<?php echo $_rqi.'forpage='.$_forPage.'&opr='.$__s['id'].'todenyfr'.$__s['stat'].'&filtering='.$_filter;?>">拒绝</a>
                        </span> 
                        | 
                        <span class="<?php if($_filter == 'pending') echo 'disabled'; ?>">
                            <a href="<?php echo $_rqi.'forpage='.$_forPage.'&opr='.$__s['id'].'topendingfr'.$__s['stat'].'&filtering='.$_filter;?>">撤销</a>
                        </span>
                    </td>
                </tr>
            <?php }
                else{
            ?>
                <tr>
                    <td class="emptyResult" colspan="7">返回了空结果。</td>
                </tr>
            <?php } ?>
        </tbody>
        <?php
            $_prevLink = $_rqi.'forpage='.($_forPage - 1 == 0 ? $_forPage : $_forPage - 1). '&filtering='. $_filter;
            $_nextLink = $_rqi.'forpage='.($_forPage == $_pages ? $_pages : $_forPage + 1). '&filtering='. $_filter;
            // 需要保留：页面名，页码，过滤条件
        
        ?>
        <tfoot>
            <tr class="foot">
                <td></td>
                <td class="<?php if($_forPage - 1 == 0) echo 'disabled'; ?> pagetool-line">
                    <a id="prev" 
                        href="<?php echo $_prevLink; ?>">
                        上一页
                    </a>
                </td>
                <td id="pagecode" class="pagetool-line">
                    <input type="number" id="pcodeInput" class="<?php if($_pages == 0) echo 'disabled'; ?>"
                        placeholder="<?php echo ($_pages == 0 ? 0 : $_forPage); ?> / <?php echo $_pages; ?>" 
                        min="<?php if($_pages == 0) echo 0; else echo 1; ?>" max="<?php echo $_pages; ?>"
                        title="最大为<?php echo $_pages; ?>，最小为<?php if($_pages == 0) echo 0; else echo 1; ?>"
                    />
                </td>
                <td class="<?php if($_forPage == $_pages || $pages == 0) echo 'disabled'; ?> pagetool-line">
                    <a id="next" 
                        href="<?php echo $_nextLink; ?>">
                        下一页
                    </a>
                </td>
                <td class="stat-line">
                    如要删除全部拒绝项目(deny)，需要先切换到【拒绝项目】过滤器
                </td>
                <td>
                    <select name="filt" id="filt">
                        <option value="pending" <?php if($_filter == 'pending') echo 'selected'; ?>>待确认</option>
                        <option value="accept" <?php if($_filter == 'accept') echo 'selected'; ?>>已接受</option>
                        <option value="deny" <?php if($_filter == 'deny') echo 'selected'; ?>>已拒绝</option>
                    </select>
                </td>
                <td class="op-line">
                    <span class="<?php if($_filter != 'deny' || $_pages == 0) echo 'disabled'; ?>">
                        <a href="<?php echo $_rqi.'forpage='.$_forPage.'&opr=0tokillall&filtering=deny';?>">删除全部拒绝项目</a>
                    </span>
                </td>
            </tr>
        </tfoot>
    </table>

    <script>
        try{
            for(let elm of document.getElementsByClassName('detailAlert')){
                elm.addEventListener('click', (e)=> {alert(`内容：\n ${e.target.innerHTML}`)});
            }
        }catch(ex){}

        document.getElementById('filt').addEventListener('change', (e) => {
            window.location.replace(`${window.location.href}`.replace(window.location.search, `?page=s-man&filtering=${e.target.value}`));
        });
        document.getElementById('pcodeInput').addEventListener('blur', (e) => {
            let v = e.target.value;
            if((v > 0 && v <= TOTAL_PAGES || TOTAL_PAGES == 0 && v == 0) && v != ''){
                if(e.target.classList.toString().includes('error')) e.target.classList.remove('error');
                if(window.location.href.includes('forpage='))
                    window.location.replace(`${window.location.href}`.replace(`forpage=${CURR_PAGE}`, `forpage=${v}`));
                else window.location.replace(`${window.location.href}&forpage=${v}`);
            }else {
                e.target.classList.add('error');
                setTimeout(() => {
                    e.target.classList.remove('error');
                }, 1234);
            }
        });
    </script>
</div>