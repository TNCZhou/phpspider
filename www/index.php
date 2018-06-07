<?php
    $order = $_GET['order']?:"_id";
    $by = $_GET['by']=='-1'?-1:1;
    $page = max(intval($_GET['page']),1);
    $keyword = htmlspecialchars($_GET['keyword']);
    $pagesize = 50;
    $_db = 'db1';
    $_collection = 'hswz';
    $mongo = new MongoDB\Driver\Manager('mongodb://localhost:27017');
    
    $filter = [];
    if($keyword) {
        $filter['title'] = new \MongoDB\BSON\Regex('.*'.$keyword.'.*');
    }
    
    /*
    $cmd = new \MongoDB\Driver\Command([
        'aggregate' => 'hswz',
        'pipeline' => [['$group' => ['_id' => '$type', 'count' => ['$sum' => 1]]]]
    ]);
    $typeOptions = [];
    $cursor = $mongo->executeCommand($_db, $cmd);
    foreach($cursor as $row) {
        $typeOptions[] = $row;
    }
    */
    
    $sort = htmlspecialchars($_GET['sort'])?:'lastreplydata';
    $by = $_GET['by'] == 'asc'?1:-1;
    
    $cursor = $mongo->executeCommand($_db, new MongoDB\Driver\Command(['count' => $_collection, 'query' => $filter]));
    $count = current($cursor->toArray())->n;
    $maxpage = max(1,ceil($count/$pagesize));
    $page = min($page,$maxpage);
    
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
    <input type='text' style='width:300px;' placeholder='查找帖子...' id='keyword' value="<?php echo $keyword; ?>"/><button onclick="doRequest()">搜索</button>
    <span>排序</span>
    <select onChange="doRequest();" id='sort'>
        <option value="_id" <?php if($sort == '_id'):?>selected<?php endif ?>>帖子ID</option>
        <option value="pubdate" <?php if($sort == 'pubdate'):?>selected<?php endif ?>>发帖时间</option>
        <option value="lastreplydata" <?php if($sort == 'lastreplydata'):?>selected<?php endif ?>>最后回帖时间</option>
        <option value="reply" <?php if($sort == 'reply'):?>selected<?php endif ?>>回复数</option>
        <option value="view" <?php if($sort == 'view'):?>selected<?php endif ?>>访问数</option>
    </select>
    <table border='1' style="margin:auto;">
        <tr>
            <th>帖子ID</th>
            <th>标题</th>
            <th>版块</th>
            <th>分类</th>
            <th>地区</th>
            <th>状态</th>
            <th>发帖人</th>
            <th>发帖时间</th>
            <th>回复数</th>
            <th>访问数</th>
            <th>最后回帖</th>
            <th>最后回帖时间</th>
        </tr>
        <?php foreach($cursor as $row): ?>
        <tr>
            <td><a target="_blank" href="view.php?id=<?php echo $row->_id; ?>"><?php echo $row->_id; ?></a></td>
            <td><a target="_blank" href="http://www.12345.suzhou.gov.cn/bbs/forum.php?mod=viewthread&tid=<?php echo $row->_id; ?>"><?php echo $row->title; ?></a></td>
            <td><?php echo $row->forum; ?></td>
            <td><?php echo $row->type; ?></td>
            <td><?php echo $row->area; ?></td>
            <td><?php echo $row->status; ?></td>
            <td><?php echo $row->author; ?></td>
            <td><?php echo is_int($row->pubdate) ? date('Y-m-d',$row->pubdate):$row->pubdate; ?></td>
            <td><?php echo $row->reply; ?></td>
            <td><?php echo $row->view; ?></td>
            <td><?php echo $row->lastreplyuser; ?></td>
            <td><?php echo is_int($row->lastreplydata) ? date('Y-m-d H:i:s',$row->lastreplydata):$row->lastreplydata; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <div class="pagination">
        <?php echo $pagination; ?>
        <span style="display:inline-block;width:100px;" ><input type='text' style="width:20%;border:none;font-size:16px;" id="jump" />/<?php echo $maxpage?>页</span><button onClick='location.href="?keyword=<?php echo $keyword?>&sort=<?php echo $sort?>&page="+document.getElementById("jump").value;'>跳转</button>
    </div>
    <script>
        function doRequest(){
            var query = 'keyword='+document.getElementById('keyword').value+'&sort='+document.getElementById("sort").value;
            location.href = '?'+query;
        }
    </script>
  </body>