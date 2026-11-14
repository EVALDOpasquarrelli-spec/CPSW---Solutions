<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
if(class_exists(\Dotenv\Dotenv::class)){\Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();}
use App\Database;
use App\Http;
use App\Auth;

$origin=getenv('FRONTEND_URL')?:'http://localhost:4200';
header('Access-Control-Allow-Origin: '.$origin);
header('Vary: Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
session_name(getenv('SESSION_NAME')?:'lirandzu_session');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax','path'=>'/']);
session_start();

try{
$db=Database::connection();
$path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/','/')?:'/';
$method=$_SERVER['REQUEST_METHOD'];
$body=in_array($method,['POST','PATCH'],true)?Http::body():[];
$auth=new Auth($db);
$audit=static function(PDO $db,?int $actor,string $action,string $type,?int $entity,array $details=[]):void{
 $s=$db->prepare('INSERT INTO audit_logs(actor_user_id,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?)');
 $s->execute([$actor,$action,$type,$entity,$details?json_encode($details,JSON_UNESCAPED_UNICODE):null,$_SERVER['REMOTE_ADDR']??null]);
};
if($path==='/api/health')Http::json(['ok'=>true,'service'=>'lirandzu-api']);
if($path==='/api/auth/login'&&$method==='POST')$auth->login($body);
if($path==='/api/auth/logout'&&$method==='POST')$auth->logout();
if($path==='/api/auth/me'&&$method==='GET')Http::json(['user'=>Http::user()]);
if($path==='/api/categories'&&$method==='GET')Http::json(['data'=>$db->query('SELECT id,name,slug,description FROM categories WHERE active=1 ORDER BY sort_order,name')->fetchAll()]);
if($path==='/api/auth/change-password'&&$method==='POST'){
$u=Http::user();$current=(string)($body['current_password']??'');$next=(string)($body['new_password']??'');
if(strlen($next)<12)Http::json(['error'=>'A nova palavra-passe deve ter pelo menos 12 caracteres.'],422);
$s=$db->prepare('SELECT password_hash FROM users WHERE id=?');$s->execute([$u['id']]);$hash=$s->fetchColumn();
if(!$hash||!password_verify($current,$hash))Http::json(['error'=>'A palavra-passe actual está incorrecta.'],403);
$s=$db->prepare('UPDATE users SET password_hash=? WHERE id=?');$s->execute([password_hash($next,PASSWORD_DEFAULT),$u['id']]);session_regenerate_id(true);Http::json(['ok'=>true,'message'=>'Palavra-passe alterada com sucesso.']);
}

if($path==='/api/providers'&&$method==='GET'){
$where=["p.status='active'"];$params=[];
foreach(['q'=>'(p.business_name LIKE ? OR p.description LIKE ?)','city'=>'p.city LIKE ?','category'=>"EXISTS (SELECT 1 FROM provider_categories pc JOIN categories cx ON cx.id=pc.category_id WHERE pc.provider_id=p.id AND cx.slug = ?)"] as $key=>$clause){$value=trim((string)($_GET[$key]??''));if($value!==''){$where[]=$clause;if($key==='q'){$params[]='%'.$value.'%';$params[]='%'.$value.'%';}elseif($key==='city')$params[]='%'.$value.'%';else$params[]=$value;}}
$sql='SELECT p.id,p.business_name,p.slug,p.city,p.description,p.indicative_price,p.cover_image,p.verified,p.featured,COALESCE((SELECT GROUP_CONCAT(c2.name ORDER BY pc2.is_primary DESC) FROM provider_categories pc2 JOIN categories c2 ON c2.id=pc2.category_id WHERE pc2.provider_id=p.id),c.name) category,c.slug category_slug FROM providers p JOIN categories c ON c.id=p.category_id WHERE '.implode(' AND ',$where).' ORDER BY p.featured DESC,p.verified DESC,p.business_name LIMIT 100';
$s=$db->prepare($sql);$s->execute($params);Http::json(['data'=>$s->fetchAll()]);
}
if(preg_match('#^/api/providers/([a-z0-9-]+)$#',$path,$m)&&$method==='GET'){
$s=$db->prepare("SELECT p.id,p.business_name,p.slug,p.city,p.province,p.district,p.description,p.indicative_price,p.cover_image,p.website_url,p.instagram_url,p.facebook_url,p.verified,p.featured,c.name category,c.slug category_slug FROM providers p JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.status='active' LIMIT 1");$s->execute([$m[1]]);$p=$s->fetch();if(!$p)Http::json(['error'=>'Fornecedor não encontrado'],404);
$q=$db->prepare("SELECT c.name,c.slug,pc.is_primary FROM provider_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.provider_id=? ORDER BY pc.is_primary DESC,c.name");$q->execute([$p['id']]);$p['categories']=$q->fetchAll();
$q=$db->prepare("SELECT id,name,description,price_from,price_to,price_unit FROM provider_services WHERE provider_id=? AND active=1 ORDER BY sort_order,name");$q->execute([$p['id']]);$p['services']=$q->fetchAll();
$q=$db->prepare("SELECT id,media_type,media_url,alt_text,is_cover,sort_order FROM provider_media WHERE provider_id=? AND approved=1 ORDER BY is_cover DESC,sort_order,id");$q->execute([$p['id']]);$p['gallery']=$q->fetchAll();
Http::json(['data'=>$p]);
}
if($path==='/api/provider-applications'&&$method==='POST'){
$n=trim((string)($body['applicant_name']??''));$b=trim((string)($body['business_name']??''));$e=strtolower(trim((string)($body['email']??'')));$ph=trim((string)($body['phone']??''));$c=(int)($body['category_id']??0);$city=trim((string)($body['city']??''));$d=trim((string)($body['description']??''));
if(strlen($n)<3||strlen($b)<2||!filter_var($e,FILTER_VALIDATE_EMAIL)||strlen($ph)<8||$c<1||strlen($city)<2||strlen($d)<20)Http::json(['error'=>'Verifique os campos da candidatura. A descrição deve ter pelo menos 20 caracteres.'],422);
$s=$db->prepare('SELECT id FROM categories WHERE id=? AND active=1');$s->execute([$c]);if(!$s->fetch())Http::json(['error'=>'Categoria inválida'],422);
$s=$db->prepare('INSERT INTO provider_applications(applicant_name,business_name,email,phone,category_id,city,description) VALUES(?,?,?,?,?,?,?)');$s->execute([$n,$b,$e,$ph,$c,$city,$d]);Http::json(['ok'=>true,'message'=>'Candidatura recebida. A equipa LIRANDZU irá analisá-la.'],201);
}
if($path==='/api/service-requests'&&$method==='POST'){
$provider=(int)($body['provider_id']??0);$name=trim((string)($body['customer_name']??''));$email=strtolower(trim((string)($body['customer_email']??'')));$phone=trim((string)($body['customer_phone']??''));$city=trim((string)($body['event_city']??''));$message=trim((string)($body['message']??''));$date=trim((string)($body['event_date']??''));$budget=$body['budget']??null;
if($provider<1||strlen($name)<3||strlen($phone)<8||strlen($city)<2||strlen($message)<10||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)))Http::json(['error'=>'Preencha correctamente os campos obrigatórios.'],422);
if($date!==''&&!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date))Http::json(['error'=>'Data do evento inválida'],422);
$s=$db->prepare("SELECT id FROM providers WHERE id=? AND status='active'");$s->execute([$provider]);if(!$s->fetch())Http::json(['error'=>'Este fornecedor não está disponível'],404);
$ref='LIR-'.strtoupper(bin2hex(random_bytes(8)));
$s=$db->prepare('INSERT INTO service_requests(reference_code,provider_id,customer_name,customer_email,customer_phone,event_date,event_city,budget,message) VALUES(?,?,?,?,?,?,?,?,?)');
$s->execute([$ref,$provider,$name,$email?:null,$phone,$date?:null,$city,($budget!==''&&$budget!==null)?(float)$budget:null,$message]);$requestId=(int)$db->lastInsertId();$h=$db->prepare("INSERT INTO request_status_history(request_id,old_status,new_status,note) VALUES(?,NULL,'forwarded','Pedido submetido pelo cliente através do catálogo público')");$h->execute([$requestId]);
Http::json(['ok'=>true,'reference_code'=>$ref,'message'=>'Pedido enviado à equipa LIRANDZU. Acompanharemos o encaminhamento ao fornecedor.'],201);
}
if($path==='/api/admin/summary'&&$method==='GET'){
Http::role('admin');$summary=[];foreach(['providers'=>"SELECT COUNT(*) FROM providers WHERE status='active'",'pending_applications'=>"SELECT COUNT(*) FROM provider_applications WHERE status='pending'",'requests'=>"SELECT COUNT(*) FROM service_requests",'featured'=>"SELECT COUNT(*) FROM providers WHERE featured=1 AND status='active'"] as $k=>$sql)$summary[$k]=(int)$db->query($sql)->fetchColumn();Http::json(['data'=>$summary]);
}
if($path==='/api/admin/applications'&&$method==='GET'){
Http::role('admin');$status=(string)($_GET['status']??'pending');if(!in_array($status,['pending','approved','rejected'],true))$status='pending';$s=$db->prepare('SELECT a.*,c.name category FROM provider_applications a JOIN categories c ON c.id=a.category_id WHERE a.status=? ORDER BY a.created_at DESC');$s->execute([$status]);Http::json(['data'=>$s->fetchAll()]);
}
if(preg_match('#^/api/admin/applications/(\\d+)/decision$#',$path,$m)&&$method==='POST'){
Http::role('admin');$id=(int)$m[1];$decision=$body['decision']??'';$notes=trim((string)($body['admin_notes']??''));if(!in_array($decision,['approved','rejected'],true))Http::json(['error'=>'Decisão inválida'],422);
$db->beginTransaction();$s=$db->prepare("SELECT * FROM provider_applications WHERE id=? AND status='pending' FOR UPDATE");$s->execute([$id]);$a=$s->fetch();if(!$a){$db->rollBack();Http::json(['error'=>'Candidatura não encontrada ou já analisada'],404);}
$s=$db->prepare('UPDATE provider_applications SET status=?,admin_notes=?,reviewed_at=NOW(),reviewed_by=? WHERE id=?');$s->execute([$decision,$notes?:null,$u['id'],$id]);$temporaryPassword=null;
if($decision==='approved'){
$s=$db->prepare('SELECT id FROM users WHERE email=?');$s->execute([$a['email']]);$userId=$s->fetchColumn();
if($userId){$db->rollBack();Http::json(['error'=>'Já existe uma conta com este e-mail. Verifique os registos antes de aprovar.'],409);}
if(!$userId){$temporaryPassword=bin2hex(random_bytes(6));$s=$db->prepare("INSERT INTO users(name,email,phone,password_hash,role,status) VALUES(?,?,?,?, 'provider','active')");$s->execute([$a['applicant_name'],$a['email'],$a['phone'],password_hash($temporaryPassword,PASSWORD_DEFAULT)]);$userId=(int)$db->lastInsertId();}
$slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',iconv('UTF-8','ASCII//TRANSLIT',$a['business_name'])?:$a['business_name']),'-')).'-'.substr(bin2hex(random_bytes(3)),0,6);
$s=$db->prepare("INSERT INTO providers(user_id,category_id,business_name,slug,city,description,status) VALUES(?,?,?,?,?,?,'active')");$s->execute([$userId,$a['category_id'],$a['business_name'],$slug,$a['city'],$a['description']]);$providerId=(int)$db->lastInsertId();$s=$db->prepare('INSERT INTO provider_categories(provider_id,category_id,is_primary) VALUES(?,?,1)');$s->execute([$providerId,$a['category_id']]);$audit($db,(int)$u['id'],'provider_application_approved','provider',$providerId,['application_id'=>$id]);
}
$db->commit();Http::json(['ok'=>true,'message'=>$decision==='approved'?'Fornecedor aprovado e publicado.':'Candidatura rejeitada.','temporary_password'=>$temporaryPassword]);
}
if($path==='/api/admin/providers'&&$method==='POST'){
$u=Http::role('admin');$name=trim((string)($body['contact_name']??''));$business=trim((string)($body['business_name']??''));$email=strtolower(trim((string)($body['email']??'')));$phone=trim((string)($body['phone']??''));$category=(int)($body['category_id']??0);$city=trim((string)($body['city']??''));$description=trim((string)($body['description']??''));
if(strlen($name)<3||strlen($business)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($phone)<8||$category<1||strlen($city)<2||strlen($description)<20)Http::json(['error'=>'Preencha correctamente os dados do fornecedor.'],422);
$s=$db->prepare('SELECT id FROM categories WHERE id=? AND active=1');$s->execute([$category]);if(!$s->fetch())Http::json(['error'=>'Categoria inválida.'],422);
$s=$db->prepare('SELECT id FROM users WHERE email=?');$s->execute([$email]);if($s->fetch())Http::json(['error'=>'Já existe uma conta com este e-mail.'],409);
$temp=bin2hex(random_bytes(8));$slugBase=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',iconv('UTF-8','ASCII//TRANSLIT',$business)?:$business),'-'));$slug=$slugBase.'-'.substr(bin2hex(random_bytes(4)),0,8);
$db->beginTransaction();$s=$db->prepare("INSERT INTO users(name,email,phone,password_hash,role,status) VALUES(?,?,?,?,'provider','active')");$s->execute([$name,$email,$phone,password_hash($temp,PASSWORD_DEFAULT)]);$uid=(int)$db->lastInsertId();$s=$db->prepare("INSERT INTO providers(user_id,category_id,business_name,slug,city,description,status) VALUES(?,?,?,?,?,?,'active')");$s->execute([$uid,$category,$business,$slug,$city,$description]);$pid=(int)$db->lastInsertId();$s=$db->prepare('INSERT INTO provider_categories(provider_id,category_id,is_primary) VALUES(?,?,1)');$s->execute([$pid,$category]);$audit($db,(int)$u['id'],'provider_created','provider',$pid,['email'=>$email]);$db->commit();Http::json(['ok'=>true,'message'=>'Fornecedor registado e publicado. Entregue a palavra-passe temporária por canal seguro.','temporary_password'=>$temp],201);
}
if($path==='/api/provider/categories'&&$method==='GET'){
$u=Http::role('provider');$s=$db->prepare('SELECT pc.category_id,c.name,c.slug,pc.is_primary FROM provider_categories pc JOIN providers p ON p.id=pc.provider_id JOIN categories c ON c.id=pc.category_id WHERE p.user_id=? ORDER BY pc.is_primary DESC,c.name');$s->execute([$u['id']]);Http::json(['data'=>$s->fetchAll()]);
}
if($path==='/api/provider/categories'&&$method==='PUT'){
$u=Http::role('provider');$ids=$body['category_ids']??[];if(!is_array($ids)||count($ids)<1||count($ids)>8)Http::json(['error'=>'Seleccione entre uma e oito categorias.'],422);$ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($v)=>$v>0)));if(!$ids||count($ids)>8)Http::json(['error'=>'Categorias inválidas.'],422);
$db->beginTransaction();$s=$db->prepare("SELECT id FROM providers WHERE user_id=? AND status='active' FOR UPDATE");$s->execute([$u['id']]);$pid=(int)$s->fetchColumn();if(!$pid){$db->rollBack();Http::json(['error'=>'Perfil indisponível.'],404);}
$in=implode(',',array_fill(0,count($ids),'?'));$s=$db->prepare("SELECT id FROM categories WHERE active=1 AND id IN ($in)");$s->execute($ids);$valid=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));if(count($valid)!==count($ids)){$db->rollBack();Http::json(['error'=>'Uma ou mais categorias são inválidas.'],422);}
$s=$db->prepare('DELETE FROM provider_categories WHERE provider_id=?');$s->execute([$pid]);$s=$db->prepare('INSERT INTO provider_categories(provider_id,category_id,is_primary) VALUES(?,?,?)');foreach($ids as $i=>$cid)$s->execute([$pid,$cid,$i===0?1:0]);$s=$db->prepare('UPDATE providers SET category_id=? WHERE id=?');$s->execute([$ids[0],$pid]);$audit($db,(int)$u['id'],'provider_categories_updated','provider',$pid,['category_ids'=>$ids]);$db->commit();Http::json(['ok'=>true]);
}
if($path==='/api/admin/providers'&&$method==='GET'){
Http::role('admin');$s=$db->query('SELECT p.id,p.business_name,p.slug,p.city,p.status,p.featured,p.verified,c.name category,u.email FROM providers p JOIN categories c ON c.id=p.category_id LEFT JOIN users u ON u.id=p.user_id ORDER BY p.created_at DESC');Http::json(['data'=>$s->fetchAll()]);
}
if(preg_match('#^/api/admin/providers/(\\d+)$#',$path,$m)&&$method==='PATCH'){
Http::role('admin');$id=(int)$m[1];$status=$body['status']??null;$featured=$body['featured']??null;$verified=$body['verified']??null;if($status!==null&&!in_array($status,['active','suspended','rejected'],true))Http::json(['error'=>'Estado inválido'],422);$s=$db->prepare('UPDATE providers SET status=COALESCE(?,status),featured=COALESCE(?,featured),verified=COALESCE(?,verified) WHERE id=?');$s->execute([$status,$featured===null?null:(int)(bool)$featured,$verified===null?null:(int)(bool)$verified,$id]);Http::json(['ok'=>true]);
}
if($path==='/api/admin/requests'&&$method==='GET'){
Http::role('admin');$s=$db->query('SELECT r.*,p.business_name FROM service_requests r JOIN providers p ON p.id=r.provider_id ORDER BY r.created_at DESC LIMIT 250');Http::json(['data'=>$s->fetchAll()]);
}
if($path==='/api/provider/profile'&&$method==='GET'){
$u=Http::role('provider');$s=$db->prepare("SELECT p.id,p.business_name,p.city,p.description,p.indicative_price,p.cover_image,c.name category FROM providers p JOIN categories c ON c.id=p.category_id WHERE p.user_id=? AND p.status='active' LIMIT 1");$s->execute([$u['id']]);Http::json(['data'=>$s->fetch()?:null]);
}
if($path==='/api/provider/profile'&&$method==='PATCH'){
$u=Http::role('provider');$name=trim((string)($body['business_name']??''));$city=trim((string)($body['city']??''));$description=trim((string)($body['description']??''));$price=$body['indicative_price']??null;$image=trim((string)($body['cover_image']??''));if(strlen($name)<2||strlen($city)<2||strlen($description)<20||($price!==null&&$price!==''&&!is_numeric($price))||($image!==''&&!filter_var($image,FILTER_VALIDATE_URL)))Http::json(['error'=>'Dados do perfil inválidos'],422);$s=$db->prepare("UPDATE providers SET business_name=?,city=?,description=?,indicative_price=?,cover_image=? WHERE user_id=? AND status='active'");$s->execute([$name,$city,$description,($price===''?null:$price),$image?:null,$u['id']]);Http::json(['ok'=>true,'message'=>'Perfil actualizado.']);
}
if($path==='/api/provider/requests'&&$method==='GET'){
$u=Http::role('provider');$s=$db->prepare("SELECT r.id,r.reference_code,r.event_date,r.event_city,r.event_province,r.event_district,r.budget,r.message,r.status,r.created_at,p.business_name FROM service_requests r JOIN providers p ON p.id=r.provider_id WHERE p.user_id=? AND p.status='active' ORDER BY r.created_at DESC");$s->execute([$u['id']]);Http::json(['data'=>$s->fetchAll()]);
}
if($path==='/api/provider/services'&&$method==='GET'){
$u=Http::role('provider');$s=$db->prepare("SELECT s.* FROM provider_services s JOIN providers p ON p.id=s.provider_id WHERE p.user_id=? ORDER BY s.sort_order,s.id");$s->execute([$u['id']]);Http::json(['data'=>$s->fetchAll()]);
}
if($path==='/api/provider/services'&&$method==='POST'){
$u=Http::role('provider');$name=trim((string)($body['name']??''));$desc=trim((string)($body['description']??''));$from=$body['price_from']??null;$to=$body['price_to']??null;$unit=(string)($body['price_unit']??'quote');$cat=isset($body['category_id'])?(int)$body['category_id']:null;
if(strlen($name)<2||!in_array($unit,['fixed','hour','day','guest','package','quote'],true)||($from!==null&&$from!==''&&!is_numeric($from))||($to!==null&&$to!==''&&!is_numeric($to))||($from!==null&&$from!==''&&(float)$from<0)||($to!==null&&$to!==''&&(float)$to<0)||($from!==null&&$from!==''&&$to!==null&&$to!==''&&(float)$to<(float)$from))Http::json(['error'=>'Dados do serviço inválidos.'],422);
$s=$db->prepare("SELECT id FROM providers WHERE user_id=? AND status='active'");$s->execute([$u['id']]);$pid=(int)$s->fetchColumn();if(!$pid)Http::json(['error'=>'Perfil de fornecedor indisponível.'],404);
$s=$db->prepare('INSERT INTO provider_services(provider_id,category_id,name,description,price_from,price_to,price_unit) VALUES(?,?,?,?,?,?,?)');$s->execute([$pid,$cat?:null,$name,$desc?:null,($from===''?null:$from),($to===''?null:$to),$unit]);Http::json(['ok'=>true,'id'=>(int)$db->lastInsertId()],201);
}
if(preg_match('#^/api/provider/services/(\\d+)$#',$path,$m)&&in_array($method,['PATCH','DELETE'],true)){
$u=Http::role('provider');$s=$db->prepare('SELECT s.id FROM provider_services s JOIN providers p ON p.id=s.provider_id WHERE s.id=? AND p.user_id=?');$s->execute([(int)$m[1],$u['id']]);if(!$s->fetch())Http::json(['error'=>'Serviço não encontrado.'],404);
if($method==='DELETE'){$s=$db->prepare('DELETE FROM provider_services WHERE id=?');$s->execute([(int)$m[1]]);Http::json(['ok'=>true]);}
$name=trim((string)($body['name']??''));$desc=trim((string)($body['description']??''));$from=$body['price_from']??null;$to=$body['price_to']??null;$unit=(string)($body['price_unit']??'quote');$active=(int)(bool)($body['active']??true);if(strlen($name)<2||!in_array($unit,['fixed','hour','day','guest','package','quote'],true)||($from!==null&&$from!==''&&!is_numeric($from))||($to!==null&&$to!==''&&!is_numeric($to)))Http::json(['error'=>'Dados do serviço inválidos.'],422);
$s=$db->prepare('UPDATE provider_services SET name=?,description=?,price_from=?,price_to=?,price_unit=?,active=? WHERE id=?');$s->execute([$name,$desc?:null,($from===''?null:$from),($to===''?null:$to),$unit,$active,(int)$m[1]]);Http::json(['ok'=>true]);
}
if($path==='/api/provider/media'&&$method==='GET'){
$u=Http::role('provider');$s=$db->prepare("SELECT m.* FROM provider_media m JOIN providers p ON p.id=m.provider_id WHERE p.user_id=? ORDER BY m.sort_order,m.id");$s->execute([$u['id']]);Http::json(['data'=>$s->fetchAll()]);
}
if($path==='/api/provider/media'&&$method==='POST'){
$u=Http::role('provider');$url=trim((string)($body['media_url']??''));$type=(string)($body['media_type']??'image');$alt=trim((string)($body['alt_text']??''));if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array($type,['image','video'],true))Http::json(['error'=>'Endereço ou tipo de ficheiro inválido.'],422);
$s=$db->prepare("SELECT id FROM providers WHERE user_id=? AND status='active'");$s->execute([$u['id']]);$pid=(int)$s->fetchColumn();if(!$pid)Http::json(['error'=>'Perfil indisponível.'],404);
$s=$db->prepare('INSERT INTO provider_media(provider_id,media_type,media_url,alt_text) VALUES(?,?,?,?)');$s->execute([$pid,$type,$url,$alt?:null]);Http::json(['ok'=>true,'id'=>(int)$db->lastInsertId()],201);
}
if(preg_match('#^/api/provider/media/(\\d+)$#',$path,$m)&&$method==='DELETE'){
$u=Http::role('provider');$s=$db->prepare('DELETE m FROM provider_media m JOIN providers p ON p.id=m.provider_id WHERE m.id=? AND p.user_id=?');$s->execute([(int)$m[1],$u['id']]);if(!$s->rowCount())Http::json(['error'=>'Imagem não encontrada.'],404);Http::json(['ok'=>true]);
}
if(preg_match('#^/api/provider/requests/(\\d+)/messages$#',$path,$m)&&in_array($method,['GET','POST'],true)){
$u=Http::role('provider');$s=$db->prepare("SELECT r.id FROM service_requests r JOIN providers p ON p.id=r.provider_id WHERE r.id=? AND p.user_id=? AND p.status='active'");$s->execute([(int)$m[1],$u['id']]);if(!$s->fetch())Http::json(['error'=>'Pedido não encontrado.'],404);
if($method==='GET'){$s=$db->prepare("SELECT id,sender_role,message,created_at FROM request_messages WHERE request_id=? AND delivery_target IN ('internal','provider') ORDER BY created_at,id");$s->execute([(int)$m[1]]);Http::json(['data'=>$s->fetchAll()]);}
$message=trim((string)($body['message']??''));if(strlen($message)<2||strlen($message)>5000)Http::json(['error'=>'A resposta deve ter entre 2 e 5000 caracteres.'],422);
$db->beginTransaction();$s=$db->prepare("INSERT INTO request_messages(request_id,sender_user_id,sender_role,delivery_target,message) VALUES(?,?,'provider','internal',?)");$s->execute([(int)$m[1],$u['id'],$message]);$s=$db->prepare("UPDATE service_requests SET status='answered' WHERE id=? AND status IN ('submitted','forwarded')");$s->execute([(int)$m[1]]);$audit($db,(int)$u['id'],'provider_request_reply','service_request',(int)$m[1]);$db->commit();Http::json(['ok'=>true,'message'=>'Resposta enviada à equipa LIRANDZU para revisão e encaminhamento.'],201);
}
if(preg_match('#^/api/admin/requests/(\\d+)/messages$#',$path,$m)&&in_array($method,['GET','POST'],true)){
$u=Http::role('admin');$rid=(int)$m[1];$s=$db->prepare('SELECT id FROM service_requests WHERE id=?');$s->execute([$rid]);if(!$s->fetch())Http::json(['error'=>'Pedido não encontrado.'],404);
if($method==='GET'){$s=$db->prepare('SELECT id,sender_user_id,sender_role,delivery_target,message,created_at FROM request_messages WHERE request_id=? ORDER BY created_at,id');$s->execute([$rid]);Http::json(['data'=>$s->fetchAll()]);}
$message=trim((string)($body['message']??''));$target=(string)($body['delivery_target']??'internal');if(strlen($message)<2||strlen($message)>5000||!in_array($target,['internal','customer','provider'],true))Http::json(['error'=>'Mensagem ou destinatário inválido.'],422);
$db->beginTransaction();$s=$db->prepare("INSERT INTO request_messages(request_id,sender_user_id,sender_role,delivery_target,message) VALUES(?,?,'admin',?,?)");$s->execute([$rid,$u['id'],$target,$message]);$s=$db->prepare("UPDATE service_requests SET status='forwarded' WHERE id=? AND ?='provider' AND status IN ('submitted','answered')");$s->execute([$rid,$target]);$audit($db,(int)$u['id'],'admin_message_sent','service_request',$rid,['delivery_target'=>$target]);$db->commit();Http::json(['ok'=>true,'message'=>'Mensagem registada e encaminhada pela LIRANDZU.'],201);
}
if(preg_match('#^/api/admin/requests/(\\d+)/status$#',$path,$m)&&$method==='PATCH'){
$u=Http::role('admin');$rid=(int)$m[1];$status=(string)($body['status']??'');$note=trim((string)($body['note']??''));if(!in_array($status,['submitted','forwarded','answered','closed','cancelled'],true))Http::json(['error'=>'Estado inválido.'],422);
$db->beginTransaction();$s=$db->prepare('SELECT status FROM service_requests WHERE id=? FOR UPDATE');$s->execute([$rid]);$old=$s->fetchColumn();if($old===false){$db->rollBack();Http::json(['error'=>'Pedido não encontrado.'],404);}$s=$db->prepare('UPDATE service_requests SET status=?,closed_at=? WHERE id=?');$s->execute([$status,$status==='closed'?date('Y-m-d H:i:s'):null,$rid]);$s=$db->prepare('INSERT INTO request_status_history(request_id,old_status,new_status,changed_by,note) VALUES(?,?,?,?,?)');$s->execute([$rid,$old,$status,$u['id'],$note?:null]);$audit($db,(int)$u['id'],'request_status_changed','service_request',$rid,['from'=>$old,'to'=>$status]);$db->commit();Http::json(['ok'=>true]);
}
if(preg_match('#^/api/service-requests/([A-Z0-9-]+)/messages$#',$path,$m)&&$method==='POST'){
$ref=$m[1];$message=trim((string)($body['message']??''));if(strlen($message)<2||strlen($message)>3000)Http::json(['error'=>'A mensagem deve ter entre 2 e 3000 caracteres.'],422);
$s=$db->prepare("SELECT id,status FROM service_requests WHERE reference_code=? LIMIT 1");$s->execute([$ref]);$request=$s->fetch();if(!$request)Http::json(['error'=>'Pedido não encontrado.'],404);if(in_array($request['status'],['closed','cancelled'],true))Http::json(['error'=>'Este pedido já foi encerrado.'],409);
$s=$db->prepare("INSERT INTO request_messages(request_id,sender_user_id,sender_role,delivery_target,message) VALUES(?,NULL,'customer','internal',?)");$s->execute([(int)$request['id'],$message]);Http::json(['ok'=>true,'message'=>'Mensagem enviada à equipa LIRANDZU.'],201);
}
if(preg_match('#^/api/service-requests/([A-Z0-9-]+)/status$#',$path,$m)&&$method==='GET'){
$ref=$m[1];$s=$db->prepare('SELECT r.reference_code,r.status,r.event_city,r.event_date,p.business_name FROM service_requests r JOIN providers p ON p.id=r.provider_id WHERE r.reference_code=? LIMIT 1');$s->execute([$ref]);$r=$s->fetch();if(!$r)Http::json(['error'=>'Pedido não encontrado.'],404);$s=$db->prepare("SELECT message,created_at FROM request_messages WHERE request_id=(SELECT id FROM service_requests WHERE reference_code=?) AND delivery_target='customer' ORDER BY created_at,id");$s->execute([$ref]);$r['messages']=$s->fetchAll();Http::json(['data'=>$r]);
}
Http::json(['error'=>'Rota não encontrada'],404);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log((string)$e);Http::json(['error'=>getenv('APP_DEBUG')==='true'?$e->getMessage():'Erro interno do servidor'],500);}
