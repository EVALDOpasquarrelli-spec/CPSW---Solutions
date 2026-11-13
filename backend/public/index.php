<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
if(class_exists(\\Dotenv\\Dotenv::class)){\\Dotenv\\Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();}
use App\\Database;
use App\\Http;
use App\\Auth;

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
if($path==='/api/health')Http::json(['ok'=>true,'service'=>'lirandzu-api']);
if($path==='/api/auth/login'&&$method==='POST')$auth->login($body);
if($path==='/api/auth/logout'&&$method==='POST')$auth->logout();
if($path==='/api/auth/me'&&$method==='GET')Http::json(['user'=>Http::user()]);
if($path==='/api/categories'&&$method==='GET')Http::json(['data'=>$db->query('SELECT id,name,slug,description FROM categories WHERE active=1 ORDER BY sort_order,name')->fetchAll()]);
if($path==='/api/providers'&&$method==='GET'){
$where=["p.status='active'"];$params=[];
foreach(['q'=>'(p.business_name LIKE ? OR p.description LIKE ?)','city'=>'p.city LIKE ?','category'=>'c.slug = ?'] as $key=>$clause){$value=trim((string)($_GET[$key]??''));if($value!==''){$where[]=$clause;if($key==='q'){$params[]='%'.$value.'%';$params[]='%'.$value.'%';}elseif($key==='city')$params[]='%'.$value.'%';else$params[]=$value;}}
$sql='SELECT p.id,p.business_name,p.slug,p.city,p.description,p.indicative_price,p.cover_image,p.verified,p.featured,c.name category,c.slug category_slug FROM providers p JOIN categories c ON c.id=p.category_id WHERE '.implode(' AND ',$where).' ORDER BY p.featured DESC,p.verified DESC,p.business_name LIMIT 100';
$s=$db->prepare($sql);$s->execute($params);Http::json(['data'=>$s->fetchAll()]);
}
if(preg_match('#^/api/providers/([a-z0-9-]+)$#',$path,$m)&&$method==='GET'){
$s=$db->prepare("SELECT p.id,p.business_name,p.slug,p.city,p.description,p.indicative_price,p.cover_image,p.verified,p.featured,c.name category,c.slug category_slug FROM providers p JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.status='active' LIMIT 1");$s->execute([$m[1]]);$p=$s->fetch();if(!$p)Http::json(['error'=>'Fornecedor não encontrado'],404);Http::json(['data'=>$p]);
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
$ref='LIR-'.strtoupper(bin2hex(random_bytes(4)));
$s=$db->prepare('INSERT INTO service_requests(reference_code,provider_id,customer_name,customer_email,customer_phone,event_date,event_city,budget,message) VALUES(?,?,?,?,?,?,?,?,?)');
$s->execute([$ref,$provider,$name,$email?:null,$phone,$date?:null,$city,($budget!==''&&$budget!==null)?(float)$budget:null,$message]);
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
$s=$db->prepare('UPDATE provider_applications SET status=?,admin_notes=?,reviewed_at=NOW() WHERE id=?');$s->execute([$decision,$notes?:null,$id]);$temporaryPassword=null;
if($decision==='approved'){
$s=$db->prepare('SELECT id FROM users WHERE email=?');$s->execute([$a['email']]);$userId=$s->fetchColumn();
if(!$userId){$temporaryPassword=bin2hex(random_bytes(6));$s=$db->prepare("INSERT INTO users(name,email,phone,password_hash,role,status) VALUES(?,?,?,?, 'provider','active')");$s->execute([$a['applicant_name'],$a['email'],$a['phone'],password_hash($temporaryPassword,PASSWORD_DEFAULT)]);$userId=(int)$db->lastInsertId();}
$slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',iconv('UTF-8','ASCII//TRANSLIT',$a['business_name'])?:$a['business_name']),'-')).'-'.substr(bin2hex(random_bytes(3)),0,6);
$s=$db->prepare("INSERT INTO providers(user_id,category_id,business_name,slug,city,description,status) VALUES(?,?,?,?,?,?,'active')");$s->execute([$userId,$a['category_id'],$a['business_name'],$slug,$a['city'],$a['description']]);
}
$db->commit();Http::json(['ok'=>true,'message'=>$decision==='approved'?'Fornecedor aprovado e publicado.':'Candidatura rejeitada.','temporary_password'=>$temporaryPassword]);
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
$u=Http::role('provider');$s=$db->prepare("SELECT r.id,r.reference_code,r.customer_name,r.customer_phone,r.customer_email,r.event_date,r.event_city,r.budget,r.message,r.status,r.created_at FROM service_requests r JOIN providers p ON p.id=r.provider_id WHERE p.user_id=? AND p.status='active' ORDER BY r.created_at DESC");$s->execute([$u['id']]);Http::json(['data'=>$s->fetchAll()]);
}
Http::json(['error'=>'Rota não encontrada'],404);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log((string)$e);Http::json(['error'=>getenv('APP_DEBUG')==='true'?$e->getMessage():'Erro interno do servidor'],500);}
