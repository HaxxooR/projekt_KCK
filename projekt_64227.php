<?php
/*
Plugin Name: Projekt w64227
Description: Dashboard umożliwiający zarządzanie zasobami i podsumowujący wydajność firmy. - MVP
Author: Kamila Tałaj w64227
Version: 1.0
*/

// Dodajemy menu w panelu administracyjnym
add_action('admin_menu', 'wykresy_acf_menu');

function wykresy_acf_menu() {
    add_menu_page(
        'Wykresy ACF',
        'Wykresy ACF',
        'manage_options',
        'wykresy-acf',
        'wykresy_acf_strona',
        'dashicons-chart-bar',
        20
    );
}

// Strona ustawień wtyczki
function wykresy_acf_strona() {
    // Sprawdzamy, czy użytkownik ma wymagane uprawnienia
    if (!current_user_can('manage_options')) {
        return;
    }

    // Sprawdzamy, czy ACF jest zainstalowane i aktywowane
    if (!function_exists('acf_add_local_field_group')) {
        echo 'Wtyczka Advanced Custom Fields (ACF) nie jest zainstalowana lub aktywowana.';
        return;
    }

    // Pobieramy wszystkie grupy pól ACF
    $field_groups = acf_get_field_groups();

    // Sprawdzamy, czy istnieją jakiekolwiek grupy pól ACF
    if (empty($field_groups)) {
        echo 'Brak dostępnych grup pól ACF.';
        return;
    }
    ?>

    <div class="wrap">
        <h1>Wykresy ACF</h1>

        <form method="post" action="options.php">
            <?php
            settings_fields('wykresy_acf_group');
            do_settings_sections('wykresy-acf');
            submit_button('Zapisz ustawienia');
            ?>
        </form>

        <?php
         //Generowanie shortcode dla każdej grupy pól ACF
       		foreach ($field_groups as $group) {
     		$group_key = $group['key'];
       		$shortcode = '[wykresy_acf grupa="' . $group_key . '"]';
       		echo '<h3>Generuj shortcode dla grupy ' . $group['title'] . ':</h3>';
     		echo '<textarea rows="3" readonly="readonly" style="width: 100%;">' . $shortcode . '</textarea>';
    		echo '<br>';
    }
     ?>
		
		<?php
// Generowanie shortcode dla każdego pola ACF
$field_groups = acf_get_field_groups();
foreach ($field_groups as $group) {
    $fields = acf_get_fields($group['key']);
    
    if (!empty($fields)) {
        foreach ($fields as $field) {
            $field_key = $field['key'];
            $shortcode = '[wykresy_acf pole="' . $field_key . '"]';
            echo '<h3>Generuj shortcode dla pola "' . $field['label'] . '" z grupy "' . $group['title'] . '":</h3>';
            echo '<textarea rows="3" readonly="readonly" style="width: 100%;">' . $shortcode . '</textarea>';
            echo '<br>';
        }
    }
}
?>
		
    </div>
    <?php
}

// Rejestracja ustawień wtyczki
add_action('admin_init', 'wykresy_acf_rejestracja_ustawien');

function wykresy_acf_rejestracja_ustawien() {
    // Sprawdzamy, czy ACF jest zainstalowane i aktywowane
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    register_setting(
        'wykresy_acf_group',
        'wykresy_acf_options',
        'wykresy_acf_sprawdz_opcje'
    );

    add_settings_section(
        'wykresy_acf_sekcja',
        'Ustawienia wykresów ACF',
        'wykresy_acf_opis_sekcji',
        'wykresy-acf'
    );

    add_settings_field(
        'wykresy_acf_grupa',
        'Grupa pól ACF',
        'wykresy_acf_pole_grupa',
        'wykresy-acf',
        'wykresy_acf_sekcja'
    );
}

// Wyświetlanie opisu sekcji
function wykresy_acf_opis_sekcji() {
    echo '<p>Wybierz grupę pól ACF, z której chcesz generować wykresy.</p>';
}

// Wyświetlanie pola wyboru grupy pól ACF
function wykresy_acf_pole_grupa() {
    $options = get_option('wykresy_acf_options');
    $grupa = isset($options['grupa']) ? $options['grupa'] : '';

    $field_groups = acf_get_field_groups();

    echo '<select name="wykresy_acf_options[grupa]">';
    echo '<option value="">-- Wybierz grupę --</option>';

    foreach ($field_groups as $group) {
        $selected = $grupa === $group['key'] ? 'selected="selected"' : '';
        echo '<option value="' . $group['key'] . '" ' . $selected . '>' . $group['title'] . '</option>';
    }

    echo '</select>';
}

// Sprawdzanie poprawności wprowadzonych opcji
function wykresy_acf_sprawdz_opcje($input) {
    // Sprawdzamy, czy opcje są zdefiniowane
    if (!isset($input['grupa'])) {
        $input['grupa'] = '';
    }

    return $input;
}

// Funkcja generująca shortcode dla wykresu
function wykresy_acf_generuj_shortcode($atts) {
    $atts = shortcode_atts(array(
        'grupa' => '',
		'pole' => ''
    ), $atts);

    $grupa = $atts['grupa'];
	$pole = $atts['pole'];

    // Sprawdzamy, czy ACF jest zainstalowane i aktywowane
    if (!function_exists('acf_add_local_field_group')) {
        return 'Wtyczka Advanced Custom Fields (ACF) nie jest zainstalowana lub aktywowana.';
    }

    // Sprawdzamy, czy wybrano grupę pól ACF
    if (empty($grupa)) {
        return 'Nie wybrano grupy pól ACF.';
    }

    // Pobieramy wpisy z wybranej grupy pól ACF
    $args = array(
        'post_type' => 'acf-field', // Tutaj możesz dostosować typ postów
//         'posts_per_page' => -1,
        'post_type' => $grupa // Klucz pola grupy ACF
    );

    $query = new WP_Query($args);

    // Generujemy wykres słupkowy
    $labels = array();
    $data = array();

		
    while ($query->have_posts()) {
        $query->the_post();
        $value = get_field($pole, get_the_ID());
        $labels[] = get_the_title();
        $data[] = $value ? intval($value) : 0;
    }

    wp_reset_postdata();

    $chart_id = 'wykres-' . uniqid(); // Unikalny identyfikator dla wykresu

    ob_start();
    ?>
    <canvas id="<?php echo $chart_id; ?>"></canvas>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById("<?php echo $chart_id; ?>").getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: '<?php echo $grupa; ?>',
                        data: <?php echo json_encode($data); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('wykresy_acf', 'wykresy_acf_generuj_shortcode');
