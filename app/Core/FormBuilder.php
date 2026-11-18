<?php
namespace App\Core;
final class FormBuilder
{
    public static function schema(string $json): array {
        if (strlen($json)>60000) throw new \InvalidArgumentException('El formulario supera el tamaño permitido.');
        try { $fields=json_decode($json,true,16,JSON_THROW_ON_ERROR); } catch (\JsonException $e) { throw new \InvalidArgumentException('Definición de formulario inválida.'); }
        if (!is_array($fields)||!array_is_list($fields)||count($fields)<1||count($fields)>30) throw new \InvalidArgumentException('Crea entre 1 y 30 campos.');
        $out=[]; $ids=[];
        foreach ($fields as $f) {
            if (!is_array($f)) throw new \InvalidArgumentException('Campo inválido.');
            $id=ConsoleView::field($f,'id',40); $type=ConsoleView::field($f,'type',20); $label=ConsoleView::field($f,'label',120);
            if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$id)||isset($ids[$id])||!in_array($type,['text','email','textarea','number','date','select','checkbox'],true)||$label===''||preg_match('/[<>]/',$label)) throw new \InvalidArgumentException('Revisa identificadores únicos, tipo y etiqueta de los campos.');
            $ids[$id]=true; $options=$f['options']??[]; if (!is_array($options)||!array_is_list($options)||count($options)>30) throw new \InvalidArgumentException('Opciones inválidas.');
            foreach ($options as $option) if (!is_string($option)||strlen($option)>150||preg_match('/[<>\x00]/',$option)) throw new \InvalidArgumentException('Opción inválida.');
            if ($type==='select'&&!count($options)) throw new \InvalidArgumentException('Añade opciones al desplegable.');
            $out[]=['id'=>$id,'type'=>$type,'label'=>$label,'required'=>!empty($f['required']),'options'=>$options];
        } return $out;
    }
    public static function validate(array $fields,array $input): array {
        $values=[]; foreach ($fields as $field) { $value=$input[$field['id']]??''; if (!is_string($value)||strlen($value)>4000) throw new \InvalidArgumentException('Valor inválido: '.$field['label']); $value=trim($value);
            if ($field['required']&&$value==='') throw new \InvalidArgumentException('Completa: '.$field['label']);
            $date=$field['type']==='date'&&$value!==''?\DateTimeImmutable::createFromFormat('!Y-m-d',$value):null;
            if ($value!=='' && (($field['type']==='email'&&!filter_var($value,FILTER_VALIDATE_EMAIL))||($field['type']==='number'&&!preg_match('/^-?\d{1,12}(?:\.\d{1,6})?$/D',$value))||($field['type']==='date'&&(!$date||$date->format('Y-m-d')!==$value))||($field['type']==='select'&&!in_array($value,$field['options'],true))||($field['type']==='checkbox'&&$value!=='1'))) throw new \InvalidArgumentException('Revisa: '.$field['label']);
            $values[$field['id']]=$value;
        } return $values;
    }
    public static function render(array $form): string {
        $fields=self::schema($form['schema_json']); $nonce=bin2hex(random_bytes(16)); $_SESSION['form_nonces']??=[]; $_SESSION['form_nonces'][$nonce]=['form'=>(int)$form['id'],'until'=>time()+3600]; if (count($_SESSION['form_nonces'])>30) array_shift($_SESSION['form_nonces']);
        $e=ConsoleView::escape(...); $html='<form class="nx-native-form" method="post" action="/forms/'.$e($form['slug']).'" style="display:grid;gap:18px;max-width:700px;padding:28px;margin:24px auto;border:1px solid #64748b;border-radius:18px"><h2>'.$e($form['name']).'</h2><input type="hidden" name="csrf_token" value="'.$e(Csrf::generate()).'"><input type="hidden" name="submission_token" value="'.$nonce.'"><input name="website_confirm" tabindex="-1" autocomplete="off" aria-hidden="true" style="display:none">';
        foreach ($fields as $f) { $id='nx-field-'.$form['id'].'-'.$f['id']; $attr=' id="'.$id.'" name="fields['.$f['id'].']" '.($f['required']?'required':'').' style="display:block;width:100%;box-sizing:border-box;padding:12px;background:#151b24;color:#f8fafc;border:1px solid #64748b;border-radius:6px"'; $html.='<label for="'.$id.'">'.$e($f['label']).($f['required']?' *':'');
            $html.=match($f['type']) { 'textarea'=>'<textarea'.$attr.' maxlength="4000" rows="5"></textarea>', 'select'=>'<select'.$attr.'><option value=""></option>'.implode('',array_map(fn($o)=>'<option value="'.$e($o).'">'.$e($o).'</option>',$f['options'])).'</select>', 'checkbox'=>'<input type="checkbox"'.$attr.' value="1" style="width:auto">',default=>'<input type="'.$f['type'].'"'.$attr.' maxlength="4000">' }; $html.='</label>'; }
        return $html.'<button type="submit" style="padding:12px;background:var(--primary,#c5ef69);color:#101317;border:0;border-radius:6px">'.Locale::text('Enviar mensaje').'</button></form>';
    }
    public static function shortcode(int $id): string { if (!FrontendEngine::isModuleActive('form-builder')) return ''; $row=Database::getInstance()->fetch('SELECT * FROM goldensite_forms WHERE id=? AND is_active=1',[$id]); return $row?self::render($row):''; }
}
