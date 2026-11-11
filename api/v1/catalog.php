<?php
declare(strict_types=1);require __DIR__.'/_bootstrap.php';api_method('GET');
$zones=all_zones();$skills=all_skills();$competition=active_competition();$stmt=db()->prepare("SELECT v.id,v.code,v.name,GROUP_CONCAT(DISTINCT s.name ORDER BY s.sort_order SEPARATOR '||') skills FROM venues v LEFT JOIN competition_skills cs ON cs.venue_id=v.id AND cs.competition_id=? LEFT JOIN skills s ON s.id=cs.skill_id WHERE v.is_active=1 GROUP BY v.id ORDER BY v.code");$stmt->execute([(int)($competition['id']??0)]);$venues=$stmt->fetchAll();foreach($venues as &$v)$v['skills']=$v['skills']?explode('||',$v['skills']):[];
json_response(['ok'=>true,'data'=>['zones'=>$zones,'skills'=>$skills,'venues'=>$venues]]);
