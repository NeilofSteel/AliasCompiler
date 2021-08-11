<?php
namespace AliasCompiler;

class Compiler
{

    public function compile($response, $main_primary_key = 'id', $primary_keys = []){
        $compiled = [];
        $joined_data = [];

        foreach($response as $i => $row) {
            $item_id = $row->{$main_primary_key};
            foreach($row as $key => $value){
                if(strpos($key, '$') !== false){

                    [$object_key, $key] = $this->explodeFirst('$', $key);

                    // Given foreign key | default: id
                    $join_pk = (!empty($primary_keys[$object_key]))? $primary_keys[$object_key] : 'id';
                    $join_item_id = $row->{"{$object_key}\${$join_pk}"};

                    if(empty($join_item_id)) continue;
                    //[$compiled_key, $value] = $this->compileJoinedObjectKeyAndValue($compiled_key, $value);
                    $item = (!empty($joined_data[$item_id][$object_key][$join_item_id]))? $joined_data[$item_id][$object_key][$join_item_id] : [];
                    $joined_data[$item_id][$object_key][$join_item_id] = $this->addCompiledKeyAndValueToItem($item, $key, $value);
                    //$joined_data[$item_id][$array_key][$join_item_id][$compiled_key] = $value;

                } else {

                    $item = (!empty($compiled[$item_id]))? $compiled[$item_id] : [];
                    $compiled[$item_id] = $this->addCompiledKeyAndValueToItem($item, $key, $value);

                }
            }
        }

        foreach($compiled as $id => $item){
            if(!empty($joined_data[$id])){
                foreach($joined_data[$id] as $joined_item_key => $joined_items){
                    foreach($joined_items as $joined_item_id => $joined_item_data){

                        $compiled[$id][$joined_item_key][] = $joined_item_data;

                    }
                }
            }
        }

        return array_values($compiled);
    }

    private function explodeFirst($separator, $string){
        $arr = explode($separator, $string);
        $first = $arr[0];
        array_splice($arr, 0, 1);
        return [$first, implode($separator, $arr)];
    }

    private function compileKeyAndValue($key, $value){
        if(strpos($key, '->') !== false || strpos($key, '>') !== false){
            if(strpos($key, '->') !== false) $key = str_replace('->', '>', $key);
            [$method, $key] = explode('>', $key);

            if($method === 'json'){
                $value = json_decode($value);
            } else if($method === 'serialized'){
                $value = unserialize($value);
            } else if($method === 'date'){
                $value = date("d.m.Y H:i", strtotime($value));
            } else if($method === 'decimal'){
                $value = $value/100;
            } else if($method === 'notempty'){
                $value = (!empty($value));
            }
        }
        return [$key, $value];
    }

    private function addCompiledKeyAndValueToItem($item, $key, $value){
        return $this->setValueToNestedKeys($item, explode('@', $key), $value);
    }

    private function setValueToNestedKeys($item, $keys, $value, $offset = 0){
        $key = $keys[$offset];
        $key_count = count($keys);
        $next_key = (($offset+1) < $key_count)? $keys[$offset+1] : null;
        if(!empty($next_key)){
            $new_item = (empty($item[$key]))? [] : $item[$key];
            $item[$key] = $this->setValueToNestedKeys($new_item, $keys, $value, $offset+1);
        } else {
            [$compiled_key, $value] = $this->compileKeyAndValue($key, $value);
            $item[$compiled_key] = $value;
        }
        return $item;
    }

}