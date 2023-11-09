<?php
/* Used for theming */

function OnlineSched_terms_list($term) {
        $tags_arr = wp_get_post_terms(get_the_ID(), $term);
        $tags = '';
        if ($tags_arr) {
                $first_entry = true;
                for ($i = 0; $i < count($tags_arr); $i++) {
                        if ($first_entry == false) {
                                $tags .= ', ';
                        }
                        $tags .= $tags_arr[$i]->name;
                        $first_entry = false;
                }
        } else {
                $tags .= 'None';
        }

        return $tags;
}

function OnlineSched_terms_list2($term, $id = 0) {
	if ($id == 0) {
		$id = get_the_ID();
	}
        $tags_arr = wp_get_post_terms($id, $term);
        $tags = '';
        if ($tags_arr) {
                $first_entry = true;
                for ($i = 0; $i < count($tags_arr); $i++) {
                        if ($first_entry == false) {
                                $tags .= ', ';
                        }
                        $tags .= $tags_arr[$i]->name;
                        $first_entry = false;
                }
        } else {
                $tags .= 'None';
        }

        return $tags;
}

function OnlineSched_terms_list_desc($term) {
	$tags_arr = wp_get_post_terms(get_the_ID(), $term);
	$tags = '';
	if ($tags_arr) {
		$first_entry = true;
		for ($i = 0; $i < count($tags_arr); $i++) {
			if ($first_entry == false) {
				$tags .= ', ';
			}
			$tags .= $tags_arr[$i]->description;
			$first_entry = false;
		}
	} else {
		$tags .= 'None';
	}

	return $tags;
}
?>
