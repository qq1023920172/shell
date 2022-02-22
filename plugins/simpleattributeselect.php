<?php

/*
 * plugin for phpList to provide the attribute selection 
 * 
 */


class simpleattributeselect extends phplistPlugin {
  public $name = "shell";
  public $coderoot = '';
  public $version = "0.1";
  public $authors = 'sarthak saini';
  public $enabled = 1;
  public $description = 'hack';
  private $numcriterias = 0;

  public $settings = array(
    "simpleattributeselect_numcriterias" => array (
      'value' => 2,
      'description' => 'Amount of criterias to use for selection',
      'type' => "integer",
      'allowempty' => 0,
      'min' => 1,
      'max' => 10,
      'category'=> 'segmentation',
    )
  );

  function __construct() {
    parent::phplistplugin();
    $this->numcriterias = 2;
    $this->numcriterias = getConfig('simpleattributeselect_numcriterias');
    $GLOBALS['simpleattributeselect_criteriacache'] = array();
  }

  function adminmenu() {
    return array(
    );
  }
  
  function upgrade($previous) {
    parent::upgrade($previous);
    return true;
  }
  
  function sendMessageTab($messageid= 0, $data= array ()) {
    global $tables,$table_prefix;
    if (!$this->enabled)
      return null;

    $criteria_content = s('<p><b>Select the criteria for this campaign:</b></p>
          <ol>
          <li>To use a criterion, check the box "use this one" next to it</li>
          <li>Then check the radio button next to the attribute you want to use</li>
          <li>Finally choose the values of the attributes you want to send the campaign to
          <i>Note:</i> Messages will be sent to people who fit to <i>Criteria 1</i> <b>AND</b> <i>Criteria 2</i> etc </li>
          </ol>').'
    <div class="accordion">
    
    ';

    $any = 0;
    for ($i=1;$i<=$this->numcriterias;$i++) {
      $criteria_content .= sprintf('<h3><a name="attr%d">%s %d</a></h3><div><table>
      ',$i,s('Criterion'),$i);
      $attributes_request = Sql_Query("select * from ".$tables['attribute']);
      $criteria_content .= sprintf('<tr><td colspan="3"><span class="fright">%s <input type="checkbox" name="criteria_use[%d]" %s /></span></td></tr>',s('Use this one'),$i,isset($data['criteria_use'][$i]) ? 'checked="checked"':'');
      while ($attribute = Sql_Fetch_array($attributes_request)) {
        $criteria_content .= "\n\n";
        $attr_type = sprintf('<input type="hidden" name="attrtype[%d]" value="%s" />',
          $attribute["id"],$attribute["type"]);
        switch ($attribute["type"]) {
          case "checkbox":
            $any = 1;
            if (!isset($data['attr_value'.$attribute["id"].$i])) {
              $data['attr_value'.$attribute["id"].$i] = 0;
            }
            $criteria_content .= sprintf ('<tr><td>%s<input type="radio" name="criteria[%d]" value="%d" %s />
               %s</td><td><b>%s</b></td><td><select name="attr_value%d%d">
                    <option value="0" %s>'.s('Not checked').'</option>
                    <option value="1" %s>'.s('Checked').'</option></select></td></tr>',
                    $attr_type,$i,$attribute["id"],
                    isset($data['criteria'][$i]) && $data['criteria'][$i] == $attribute['id'] ? 'checked="checked"': '',
                    strip_tags($attribute["name"]),s('is'),
                    $attribute["id"],$i,
                    $data['attr_value'.$attribute["id"].$i] == "0" ? 'selected="selected"': '',
                    $data['attr_value'.$attribute["id"].$i] == "1" ? 'selected="selected"': ''
                    );
            break;
          case "select":
          case "radio":
          case "checkboxgroup":
            $some = 0;
            $thisone = "";
            $values_request = Sql_Query("select * from $table_prefix"."listattr_".$attribute["tablename"]);
            $thisone .= sprintf ('<tr><td>%s <input type="radio" name="criteria[%d]" value="%d" %s /> %s</td>
                    <td><b>%s</b></td><td><select name="attr_value%d%d[]" size="4" multiple="multiple" />',
                    $attr_type,$i,$attribute["id"],
                    isset($data['criteria'][$i]) && $data['criteria'][$i] == $attribute['id'] ? 'checked="checked"': '',
                    strip_tags($attribute["name"]),s('is'),$attribute["id"],$i);
            if (isset($data['attr_value'.$attribute["id"].$i])) {
              $selected_values = $data['attr_value'.$attribute["id"].$i];
            } else {
              $selected_values = array();
            }
            
            while ($value = Sql_Fetch_array($values_request)) {
              $some =1;
              $thisone .= sprintf ('<option value="%d" %s>%s</option>',$value["id"],in_array($value['id'],$selected_values) ? 'selected="selected"':'',strip_tags($value["name"]));
            }
            $thisone .= "</select></td></tr>";
            if ($some)
              $criteria_content .= $thisone;
            $any = $any || $some;
            break;
          default:
            $criteria_content .= "\n<!-- error: huh, unknown type ".$attribute["type"]." -->\n";
        }
      }
      $criteria_content .= '</table></div>';
    }

    if (!$any) {
      $criteria_content = "<p>".$GLOBALS['I18N']->get('There are currently no attributes available to use for sending. The campaign will go to any subscriber on the lists selected')."</p>";
#    } else {
#      $criteria_content .= '</table>';
    }
    return $criteria_content .'</div><!-- close accordion -->';
  }

  function sendMessageTabTitle($messageid = 0) {
    if (!$this->enabled)
      return null;

    return s('Criteria');
  }

  function sendMessageTabSave($messageid = 0, $data = array ()) {
    if (!$this->enabled)
      return null;

    return true;
  }
  
  function subscriberSelection($messagedata) {
      global $tables;
      # check the criterias, create the selection query
      $count_query = '';
      $used_tables = array();
      for ($i=1;$i<=$this->numcriterias;$i++) {
        if (isset($messagedata["criteria_use"][$i])) {
          $attribute = $messagedata["criteria"][$i];
          $type = $messagedata["attrtype"][$attribute];
          switch($type) {
            case "checkboxgroup":
              $values = "attr_value$attribute$i";
              $or_clause = '';
              if (isset($where_clause)) {
                $where_clause .= " and ";
                $select_clause .= " left join $tables[user_attribute] as table$i on table$first.userid = table$i.userid ";
              } else {
                $select_clause = "table$i.userid from $tables[user_attribute] as table$i ";
                $first = $i;
              }

              $where_clause .= "table$i.attributeid = $attribute and (";
              if (is_array($messagedata[$values])) {
                foreach ($messagedata[$values] as $val) {
                  if ($or_clause != '') {
                    $or_clause .= " or ";
                  }
                  $or_clause .= "find_in_set('$val',table$i.value) > 0";
                }
              }
              $where_clause .= $or_clause . ")";
              break;
            case "checkbox":
              $values = "attr_value$attribute$i";
              $value = $messagedata[$values][0];

              if (isset($where_clause)) {
                $where_clause .= " and ";
                $select_clause .= " left join $tables[user_attribute] as table$i on table$first.userid = table$i.userid ";
              } else {
                $select_clause = "table$i.userid from $tables[user_attribute] as table$i ";
                $first = $i;
              }

              $where_clause .= "table$i.attributeid = $attribute and ";
              if ($value) {
                $where_clause .= sprintf('( length(table%1$d.value) and table%1$d.value != "off" and table%1$d.value != "0") ',$i);
              } else {
                $where_clause .= sprintf('( table%1$d.value = "" or table%1$d.value = "0" or table%1$d.value = "off") ',$i);
              }
              break;
             default:
              $values = "attr_value$attribute$i";
              if (isset($where_clause)) {
                $where_clause .= " and ";
                $select_clause .= " left join $tables[user_attribute] as table$i on table$first.userid = table$i.userid ";
              } else {
                $select_clause = "table$i.userid from $tables[user_attribute] as table$i ";
                $first = $i;
              }

              $where_clause .= "table$i.attributeid = $attribute and table$i.value in (";
              $list = array();
              if (is_array($messagedata[$values])) {
                while (list($key,$val) = each ($messagedata[$values]))
                  array_push($list,$val);
              }
              $where_clause .= join(", ",$list) . ")";
          }
        }
      }
      # if no selection was made, use all
      if (empty($where_clause)) {
        $count_query = "";
      } else {
        $count_query = "select $select_clause where $where_clause";
        Sql_query(sprintf('update %s set userselection = "%s" where id = %d',
          $tables["message"],
          sql_escape($count_query),
          $messagedata['id']));
      }
     # commented, because this could take too long
     # Sql_Query($count_query);
     # $num = Sql_Affected_rows();
     return $count_query;
   }
    
  
  function canSend ($messagedata, $userdata) {
    if (empty($messagedata["criteria_use"][1]) || $this->numcriterias <= 0) return true; // no criteria used
    
    if (!isset($GLOBALS['simpleattributeselect_criteriacache'][$messagedata['id']])) {
      $GLOBALS['simpleattributeselect_criteriacache'][$messagedata['id']] = array();
      $sql = $this->subscriberSelection($messagedata);
      if (!empty($sql)) {
        $req = Sql_Query($sql);
        while ($row = Sql_Fetch_Row($req)) {
          $GLOBALS['simpleattributeselect_criteriacache'][$messagedata['id']][] = $row[0];
        }
      }
    }
    //ob_end_clean();
    //var_dump($GLOBALS['simpleattributeselect_criteriacache'][$messagedata['id']]);
    $cansend = sizeof($GLOBALS['simpleattributeselect_criteriacache'][$messagedata['id']]) && in_array($userdata['id'],$GLOBALS['simpleattributeselect_criteriacache'][$messagedata['id']]);
    if (VERBOSE) {
      if ($cansend) {
        cl_output('simpleattributeselect - CAN SEND '.$userdata['id']);
      } else {
        cl_output('simpleattributeselect - CAN NOT SEND '.$userdata['id']);
      }
    }
    return $cansend;
  }
  

}
