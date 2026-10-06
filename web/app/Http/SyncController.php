<?php
declare(strict_types=1);

namespace Brassica\Http;

use Brassica\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class SyncController
{
    public static function manifest(): void
    {
        self::json(function (PDO $db, int $userId): array {
            $stmt=$db->prepare('SELECT r.* FROM recipes r WHERE r.owner_id=:uid ORDER BY r.updated_at DESC');
            $stmt->execute([':uid'=>$userId]);
            $items=[];
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
                $row=self::ensureIdentity($db,$row);
                $cats=self::categories($db,(int)$row['id']);
                $hash=self::contentHash($row,$cats);
                if(($row['content_hash']??'')!==$hash){$db->prepare('UPDATE recipes SET content_hash=:h WHERE id=:id')->execute([':h'=>$hash,':id'=>$row['id']]);}
                $items[]=['uuid'=>$row['uuid'],'title'=>$row['title'],'contentHash'=>$hash,'imageHash'=>self::imageHash($row['image_path']??null),'updatedAt'=>$row['updated_at'],'categories'=>$cats];
            }
            return ['version'=>1,'items'=>$items];
        });
    }

    public static function recipe(array $params): void
    {
        self::json(function(PDO $db,int $userId) use($params): array{
            $uuid=(string)($params['uuid']??'');
            $stmt=$db->prepare('SELECT * FROM recipes WHERE owner_id=:uid AND uuid=:uuid LIMIT 1');$stmt->execute([':uid'=>$userId,':uuid'=>$uuid]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row){http_response_code(404);throw new RuntimeException('Rezept nicht gefunden.');}
            $data=json_decode((string)$row['json_data'],true); if(!is_array($data))$data=self::rowToData($row);
            $data['categories']=array_map(static fn(string $n)=>['name'=>$n],self::categories($db,(int)$row['id']));
            $imageBase64=null;$imageName=null;
            if(!empty($row['image_path'])){$file=legacy_image_file((string)$row['image_path']);if(is_file($file)){$imageBase64=base64_encode((string)file_get_contents($file));$imageName=basename($file);}}
            return ['uuid'=>$row['uuid'],'data'=>$data,'imageName'=>$imageName,'imageBase64'=>$imageBase64,'updatedAt'=>$row['updated_at']];
        });
    }

    public static function apply(): void
    {
        self::json(function(PDO $db,int $userId): array{
            $input=json_decode((string)file_get_contents('php://input'),true);
            $recipes=is_array($input['recipes']??null)?$input['recipes']:[];$saved=[];
            foreach($recipes as $item){if(!is_array($item))continue;$saved[]=self::upsert($db,$userId,$item);}
            return ['saved'=>$saved,'count'=>count($saved)];
        });
    }

    private static function upsert(PDO $db,int $userId,array $item): string
    {
        $uuid=trim((string)($item['uuid']??''));if($uuid==='')$uuid=self::uuid();
        $data=$item['data']??null;if(!is_array($data)||trim((string)($data['title']??''))==='')throw new RuntimeException('Ungültiges Rezept.');
        $cats=[];foreach(($data['categories']??[]) as $c){$n=trim((string)(is_array($c)?($c['name']??''):$c));if($n!=='')$cats[$n]=$n;}$cats=array_values($cats);
        $now=(new \DateTimeImmutable())->format('c');
        $stmt=$db->prepare('SELECT id,image_path,created_at FROM recipes WHERE owner_id=:uid AND uuid=:uuid LIMIT 1');$stmt->execute([':uid'=>$userId,':uuid'=>$uuid]);$existing=$stmt->fetch(PDO::FETCH_ASSOC);
        $json=$data;$json['categories']=array_map(static fn($n)=>['name'=>$n],$cats);
        $values=[':uid'=>$userId,':uuid'=>$uuid,':title'=>$data['title'],':description'=>$data['description']??'',':directions'=>$data['directions']??'',':ingredients'=>$data['ingredients']??'',':notes'=>$data['notes']??'',':nutritional_vals'=>$data['nutritionalValues']??'',':preparation_time'=>$data['preparationTime']??'',':servings'=>$data['servings']??'',':source'=>$data['source']??'',':favorite'=>!empty($data['favorite'])?1:0,':json'=>json_encode($json,JSON_UNESCAPED_UNICODE),':updated'=>$now];
        if($existing){
            $id=(int)$existing['id'];$values[':id']=$id;
            $db->prepare('UPDATE recipes SET uuid=:uuid,title=:title,description=:description,directions=:directions,ingredients=:ingredients,notes=:notes,nutritional_vals=:nutritional_vals,preparation_time=:preparation_time,servings=:servings,source=:source,favorite=:favorite,json_data=:json,updated_at=:updated WHERE id=:id AND owner_id=:uid')->execute($values);
        }else{
            $values[':created']=$now;
            $db->prepare("INSERT INTO recipes(owner_id,uuid,title,description,directions,ingredients,notes,nutritional_vals,preparation_time,servings,source,favorite,json_data,source_type,created_at,updated_at) VALUES(:uid,:uuid,:title,:description,:directions,:ingredients,:notes,:nutritional_vals,:preparation_time,:servings,:source,:favorite,:json,'sync',:created,:updated)")->execute($values);$id=(int)$db->lastInsertId();
        }
        $db->prepare('DELETE FROM recipe_categories WHERE recipe_id=:id')->execute([':id'=>$id]);
        foreach($cats as $name){$q=$db->prepare('SELECT id FROM categories WHERE name=:n');$q->execute([':n'=>$name]);$cid=$q->fetchColumn();if($cid===false){$db->prepare('INSERT INTO categories(name) VALUES(:n)')->execute([':n'=>$name]);$cid=$db->lastInsertId();}$db->prepare('INSERT OR IGNORE INTO recipe_categories(recipe_id,category_id) VALUES(:r,:c)')->execute([':r'=>$id,':c'=>$cid]);}
        if(!empty($item['imageBase64'])){$raw=base64_decode((string)$item['imageBase64'],true);if($raw!==false){$name=basename((string)($item['imageName']??($uuid.'.jpg')));$name=preg_replace('/[^A-Za-z0-9._-]/','_',$name)?:($uuid.'.jpg');$path=storage_path('images/'.$uuid.'_'.$name);if(!is_dir(dirname($path)))mkdir(dirname($path),0775,true);file_put_contents($path,$raw);$stored='data/images/'.basename($path);$db->prepare('UPDATE recipes SET image_path=:p,image_name_orig=:n WHERE id=:id')->execute([':p'=>$stored,':n'=>$name,':id'=>$id]);}}
        else {$db->prepare('UPDATE recipes SET image_path=NULL,image_name_orig=NULL WHERE id=:id')->execute([':id'=>$id]);}
        $row=$db->query('SELECT * FROM recipes WHERE id='.(int)$id)->fetch(PDO::FETCH_ASSOC);$hash=self::contentHash($row,$cats);$db->prepare('UPDATE recipes SET content_hash=:h WHERE id=:id')->execute([':h'=>$hash,':id'=>$id]);
        return $uuid;
    }

    private static function json(callable $fn): void
    {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
        try{$db=Database::connection();$uid=self::basicUser($db);echo json_encode($fn($db,$uid),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
        catch(Throwable $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
    }
    private static function basicUser(PDO $db): int
    {
        $user=$_SERVER['PHP_AUTH_USER']??null;$pass=$_SERVER['PHP_AUTH_PW']??null;
        if($user===null&&isset($_SERVER['HTTP_AUTHORIZATION'])&&str_starts_with($_SERVER['HTTP_AUTHORIZATION'],'Basic ')){$decoded=base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'],6),true);if($decoded!==false&&str_contains($decoded,':'))[$user,$pass]=explode(':',$decoded,2);}
        if($user===null){http_response_code(401);header('WWW-Authenticate: Basic realm="Brassica Sync"');throw new RuntimeException('Anmeldung erforderlich.');}
        $stmt=$db->prepare('SELECT id,password_hash FROM users WHERE username=:u LIMIT 1');$stmt->execute([':u'=>$user]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row||!password_verify((string)$pass,(string)$row['password_hash'])){http_response_code(401);throw new RuntimeException('Ungültige Zugangsdaten.');}return(int)$row['id'];
    }
    private static function ensureIdentity(PDO $db,array $row): array{if(empty($row['uuid'])){$row['uuid']=self::uuid();$db->prepare('UPDATE recipes SET uuid=:u WHERE id=:id')->execute([':u'=>$row['uuid'],':id'=>$row['id']]);}return$row;}
    private static function uuid(): string{$b=random_bytes(16);$b[6]=chr((ord($b[6])&0x0f)|0x40);$b[8]=chr((ord($b[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));}
    private static function categories(PDO $db,int $id): array{$s=$db->prepare('SELECT c.name FROM categories c JOIN recipe_categories rc ON rc.category_id=c.id WHERE rc.recipe_id=:id ORDER BY c.name COLLATE NOCASE');$s->execute([':id'=>$id]);return$s->fetchAll(PDO::FETCH_COLUMN);}
    private static function contentHash(array $row,array $cats): string{$data=self::rowToData($row);$data['categories']=array_map(static fn($n)=>['name'=>$n],$cats);return hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    private static function imageHash(?string $stored): ?string{if(!$stored)return null;$f=legacy_image_file($stored);return is_file($f)?hash_file('sha256',$f):null;}
    private static function rowToData(array $r): array{return['title'=>$r['title']??'','description'=>$r['description']??'','directions'=>$r['directions']??'','ingredients'=>$r['ingredients']??'','notes'=>$r['notes']??'','nutritionalValues'=>$r['nutritional_vals']??'','preparationTime'=>$r['preparation_time']??'','servings'=>$r['servings']??'','source'=>$r['source']??'','favorite'=>!empty($r['favorite'])];}
}
