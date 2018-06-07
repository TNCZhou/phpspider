<?php
    $tid = intval($_GET['id']);
    if(!$tid) {
        header('location:index.php');exit;
    }
    $page = max(intval($_GET['page']),1);
    $pagesize = 20;
    $_db = 'db1';
    $_collection = 'hswzthread';
    $mongo = new MongoDB\Driver\Manager('mongodb://localhost:27017');
    
    $filter = ['threadid' => $tid];
    
    $sort = htmlspecialchars($_GET['sort'])?:'_id';
    $by = $_GET['by'] == 'desc'?-1:1;
    $cursor = $mongo->executeCommand($_db, new MongoDB\Driver\Command(['count' => $_collection, 'query' => $filter]));
    $count = current($cursor->toArray())->n;
    $maxpage = max(1,ceil($count/$pagesize));
    $page = min($page,$maxpage);
    
    $cursor = $mongo->executeQuery($_db.'.hswz',new MongoDB\Driver\Query(['_id' => $tid]));
    $thread = current($cursor->toArray());
    
    $query = new MongoDB\Driver\Query($filter,['sort' => [$sort => $by],'limit' => $pagesize,'skip'=>($page-1)*$pagesize]);
    $cursor = $mongo->executeQuery($_db.'.'.$_collection, $query);
    
    $showpages = 5;
    $pagination = "";
    $_GET['page'] = '1';
    $firstpage = "<span><a href='?".http_build_query($_GET)."'>1...</a></span>";
    $_GET['page'] = $maxpage;
    $lastpage = "<span><a href='?".http_build_query($_GET)."'>...".$maxpage."</a></span>";
    if($page>$showpages+1)
        $pagination .= $firstpage;
    for($i=max(1,$page-$showpages);$i<=min($page+5,$maxpage);$i++) {
        $_GET['page'] = $i;
        $pagination .= $page==$i?"<span>".$i."</span>":"<span><a href='?".http_build_query($_GET)."'>".$i."</a></span>";
    }
    if($page<$maxpage-5){
        $pagination .= $lastpage;
    }
    $pagination .= "<span>共".$count."条</span>";
?>
<html>
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
  </head>
  <style>
    .pagination {
        margin-top: 3px;
        text-align:center;
    }
    .pagination span {
        margin: 0 5px;
        padding: 0 5px;
        border: 1px solid #2e2e2e;
    }
  </style>
  <body>
    <h3 style="text-align:center">寒山闻钟</h3>
    <div style="text-align:center">
    <table border='1' style="margin:auto;">
        <tr >
            <th>查看:<?php echo $thread->view?>   回复:<?php echo $thread->reply?></th>
            <th>[<?php echo $thread->type; ?>][<?php echo $thread->area; ?>]<?php echo $thread->title; ?></th>
        </tr>
        <?php foreach($cursor as $row): ?>
        <tr>
            <td>
                <div>
                    <img src="<?php echo $row->avatar; ?>">
                </div>
                <div>
                    <span><a href="http://www.12345.suzhou.gov.cn/bbs/home.php?mod=space&uid=<?php echo $row->uid?>"><?php echo $row->username; ?></a></span>
                </div>
            </td>
            <td>
                <div>发布于 <span><?php echo date('Y-m-d H:i:s',$row->pubdate);?></span></div>
                <div><?php echo $row->content; ?></div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <div class="pagination">
        <?php echo $pagination; ?>
        <span style="display:inline-block;width:100px;" ><input type='text' style="width:20%;border:none;font-size:16px;" id="jump" />/<?php echo $maxpage?>页</span><button onClick='location.href="?id=<?php echo $tid?>&page="+document.getElementById("jump").value;'>跳转</button>
    </div>
    <script>
        function doRequest(page){
            var query = 'keyword='+document.getElementById('keyword').value+'&sort='+document.getElementById("sort").value;
            location.href = '?'+query;
        }
    </script>
  </body>