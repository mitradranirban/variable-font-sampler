<?php
/**
 * Plugin Name: Variable Font Sampler
 * Plugin URI: https://mitradranirban.github.io/variable-font-sampler
 * Description: A WordPress plugin for showcasing variable fonts using fontsampler.js library with interactive controls.
 * Version: 1.0.3
 * Author: Dr Anirban Mitra
 * License: GPL v3 or later
 * Text Domain: variable_font_sampler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Varifosa_Sampler {
    
    private $plugin_url;
    private $plugin_path;

    // Uploads directory (for generated assets)
    private $plugin_upload_dir;
    private $plugin_upload_url;
    
    public function __construct() {
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);

        // Setup uploads directory paths/urls for assets
        $upload_dir = wp_upload_dir();
        $this->plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        $this->plugin_upload_url = trailingslashit($upload_dir['baseurl']) . 'variable-font-sampler/';
        if (!file_exists($this->plugin_upload_dir)) {
            wp_mkdir_p($this->plugin_upload_dir);
        }
        
        add_action('init', array($this, 'varifosa_init'));
        add_action('wp_enqueue_scripts', array($this, 'varifosa_enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'varifosa_admin_enqueue_scripts'));
        add_shortcode('font_sampler', array($this, 'varifosa_font_sampler_shortcode'));
        add_action('admin_menu', array($this, 'varifosa_add_admin_menu'));
        add_action('admin_init', array($this, 'varifosa_admin_init'));
        
        register_activation_hook(__FILE__, array($this, 'varifosa_activate'));
        register_deactivation_hook(__FILE__, array($this, 'varifosa_deactivate'));
    }
    
    public function varifosa_init() {
        // load_plugin_textdomain() call removed
    }
    
    public function varifosa_enqueue_scripts() {
        // Enqueue our custom font sampler script and CSS from uploads directory
        $upload_dir = wp_upload_dir();
        $plugin_upload_url = trailingslashit($upload_dir['baseurl']) . 'variable-font-sampler/';
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        
        // Ensure files exist (in case activation did not run)
        if (!file_exists($plugin_upload_dir . 'font-sampler.css')) {
            $this->varifosa_create_css_file();
        }
        if (!file_exists($plugin_upload_dir . 'font-sampler.js')) {
            $this->varifosa_create_js_file();
        }

        wp_enqueue_style(
            'font-sampler',
            $plugin_upload_url . 'font-sampler.css',
            array(),
            null
        );
        wp_enqueue_script(
            'font-sampler',
            $plugin_upload_url . 'font-sampler.js',
            array('jquery'),
            null,
            true
        );
        
        // Localize script for AJAX (if needed)
        wp_localize_script('font-sampler', 'fontSampler', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('varifosa_font_sampler_nonce')
        ));
    }
    
    public function varifosa_admin_enqueue_scripts($hook) {
        if ('settings_page_variable-font-sampler' !== $hook) {
            return;
        }
        $upload_dir = wp_upload_dir();
        $plugin_upload_url = trailingslashit($upload_dir['baseurl']) . 'variable-font-sampler/';
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        
        // Ensure admin.js file exists
        if (!file_exists($plugin_upload_dir . 'admin.js')) {
            $this->varifosa_create_admin_js_file();
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'variable-font-sampler-admin',
            $plugin_upload_url . 'admin.js',
            array('jquery'),
            null,
            true
        );
    }
    
    public function varifosa_font_sampler_shortcode($atts) {
        $atts = shortcode_atts(array(
            'font' => '',
            'text' => 'The quick brown fox jumps over the lazy dog',
            'size' => '32',
            'controls' => 'true',
            'id' => uniqid('font-sampler-')
        ), $atts);
        
        $font_url = $atts['font'] ? $atts['font'] : get_option('varifosa_default_font', '');
        
        if (empty($font_url)) {
            return '<p>' . esc_html__('No font specified. Please add a font URL or set a default font in the plugin settings.', 'variable_font_sampler') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="font-sampler-container" id="<?php echo esc_attr($atts['id']); ?>">
            <div class="font-sampler-preview">
                <div class="font-sample" 
                     data-font="<?php echo esc_url($font_url); ?>"
                     data-text="<?php echo esc_attr($atts['text']); ?>"
                     data-size="<?php echo esc_attr($atts['size']); ?>"
                     data-controls="<?php echo esc_attr($atts['controls']); ?>">
                    <?php echo esc_html($atts['text']); ?>
                </div>
            </div>
            
            <?php if ($atts['controls'] === 'true'): ?>
            <div class="font-sampler-controls">
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-size"><?php esc_html_e('Font Size:', 'variable_font_sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-size" 
                           class="size-control" min="12" max="120" value="<?php echo esc_attr($atts['size']); ?>">
                    <span class="size-value"><?php echo esc_html($atts['size']); ?>px</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-weight"><?php esc_html_e('Font Weight:', 'variable_font_sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-weight" 
                           class="weight-control" min="100" max="900" value="400" step="100">
                    <span class="weight-value">400</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-width"><?php esc_html_e('Font Width:', 'variable_font_sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-width" 
                           class="width-control" min="50" max="200" value="100">
                    <span class="width-value">100%</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-text"><?php esc_html_e('Sample Text:', 'variable_font_sampler'); ?></label>
                    <input type="text" id="<?php echo esc_attr($atts['id']); ?>-text" 
                           class="text-control" value="<?php echo esc_attr($atts['text']); ?>">
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function varifosa_add_admin_menu() {
        add_options_page(
            esc_html__('Variable Font Sampler', 'variable_font_sampler'),
            esc_html__('Font Sampler', 'variable_font_sampler'),
            'manage_options',
            'variable-font-sampler',
            array($this, 'varifosa_admin_page')
        );
    }
    
    public function varifosa_admin_init() {
        // Register settings with proper sanitization callbacks
        register_setting('varifosa_settings', 'varifosa_default_font', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'varifosa_sanitize_font_url'),
            'default' => ''
        ));
        
        register_setting('varifosa_settings', 'varifosa_custom_fonts', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'varifosa_sanitize_custom_fonts'),
            'default' => array()
        ));
        
        add_settings_section(
            'varifosa_main_section',
            esc_html__('Font Settings', 'variable_font_sampler'),
            array($this, 'varifosa_settings_section_callback'),
            'variable-font-sampler'
        );
        
        add_settings_field(
            'varifosa_default_font',
            esc_html__('Default Font URL', 'variable_font_sampler'),
            array($this, 'varifosa_default_font_callback'),
            'variable-font-sampler',
            'varifosa_main_section'
        );
        
        add_settings_field(
            'varifosa_custom_fonts',
            esc_html__('Custom Fonts', 'variable_font_sampler'),
            array($this, 'varifosa_custom_fonts_callback'),
            'variable-font-sampler',
            'varifosa_main_section'
        );
    }
    
    /**
     * Sanitize font URL
     */
    public function varifosa_sanitize_font_url($input) {
        if (empty($input)) {
            return '';
        }
        
        $url = esc_url_raw($input);
        
        // Validate that it's a font file
        $allowed_extensions = array('woff', 'woff2', 'ttf', 'otf', 'eot');
        $parsed_url = wp_parse_url($url);
        $file_extension = pathinfo($parsed_url['path'], PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            add_settings_error(
                'varifosa_default_font',
                'invalid_font_url',
                esc_html__('Invalid font file. Please upload a valid font file (.woff, .woff2, .ttf, .otf, .eot)', 'variable_font_sampler')
            );
            return get_option('varifosa_default_font', '');
        }
        
        return $url;
    }
    
    /**
     * Sanitize custom fonts array
     */
    public function varifosa_sanitize_custom_fonts($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $sanitized = array();
        $allowed_extensions = array('woff', 'woff2', 'ttf', 'otf', 'eot');
        
        foreach ($input as $font) {
            if (!is_array($font) || empty($font['name']) || empty($font['url'])) {
                continue;
            }
            
            $name = sanitize_text_field($font['name']);
            $url = esc_url_raw($font['url']);
            
            // Validate font URL
            $parsed_url = wp_parse_url($url);
            $file_extension = pathinfo($parsed_url['path'], PATHINFO_EXTENSION);
            
            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $sanitized[] = array(
                    'name' => $name,
                    'url' => $url
                );
            }
        }
        
        return $sanitized;
    }
    
    public function varifosa_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Variable Font Sampler Settings', 'variable_font_sampler'); ?></h1>
            
            <div class="usage-info">
                <h3><?php esc_html_e('Usage Instructions', 'variable_font_sampler'); ?></h3>
                <p><?php esc_html_e('Use the shortcode to display font samples:', 'variable_font_sampler'); ?></p>
                <code>[font_sampler font="URL_TO_FONT" text="Sample text" size="32" controls="true"]</code>
                <p><?php esc_html_e('Parameters:', 'variable_font_sampler'); ?></p>
                <ul>
                    <li><strong>font:</strong> <?php esc_html_e('URL to the variable font file (optional if default is set)', 'variable_font_sampler'); ?></li>
                    <li><strong>text:</strong> <?php esc_html_e('Sample text to display (default: "The quick brown fox...")', 'variable_font_sampler'); ?></li>
                    <li><strong>size:</strong> <?php esc_html_e('Initial font size in pixels (default: 32)', 'variable_font_sampler'); ?></li>
                    <li><strong>controls:</strong> <?php esc_html_e('Show interactive controls (default: true)', 'variable_font_sampler'); ?></li>
                </ul>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('varifosa_settings');
                do_settings_sections('variable-font-sampler');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function varifosa_settings_section_callback() {
        echo '<p>' . esc_html__('Configure your variable font settings below.', 'variable_font_sampler') . '</p>';
    }
    
    public function varifosa_default_font_callback() {
        $value = get_option('varifosa_default_font', '');
        echo '<input type="url" name="varifosa_default_font" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<button type="button" class="button upload-font-btn">' . esc_html__('Upload Font', 'variable_font_sampler'); ?></button>';
        echo '<p class="description">' . esc_html__('Enter the URL to your default variable font file (.woff2, .woff, .ttf)', 'variable_font_sampler') . '</p>';
    }
    
    public function varifosa_custom_fonts_callback() {
        $fonts = get_option('varifosa_custom_fonts', array());
        echo '<div id="custom-fonts-container">';
        
        if (!empty($fonts)) {
            foreach ($fonts as $index => $font) {
                $this->varifosa_render_font_input($index, $font);
            }
        } else {
            $this->varifosa_render_font_input(0, array('name' => '', 'url' => ''));
        }
        
        echo '</div>';
        echo '<button type="button" class="button add-font-btn">' . esc_html__('Add Another Font', 'variable_font_sampler'); ?></button>';
    }
    
    private function varifosa_render_font_input($index, $font) {
        ?>
        <div class="font-input-group">
            <input type="text" name="varifosa_custom_fonts[<?php echo esc_attr($index); ?>][name]" 
                   value="<?php echo esc_attr($font['name']); ?>" 
                   placeholder="<?php esc_attr_e('Font Name', 'variable_font_sampler'); ?>" />
            <input type="url" name="varifosa_custom_fonts[<?php echo esc_attr($index); ?>][url]" 
                   value="<?php echo esc_attr($font['url']); ?>" 
                   placeholder="<?php esc_attr_e('Font URL', 'variable_font_sampler'); ?>" />
            <button type="button" class="button remove-font-btn"><?php esc_html_e('Remove', 'variable_font_sampler'); ?></button>
        </div>
        <?php
    }
    
    public function varifosa_activate() {
        // Create uploads directory structure
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        // Create CSS file
        $this->varifosa_create_css_file();
        
        // Create JS file
        $this->varifosa_create_js_file();
        
        // Create admin JS file
        $this->varifosa_create_admin_js_file();
    }
    
    public function varifosa_deactivate() {
        // Clean up if needed
    }
    
    private function varifosa_create_css_file() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        $css_content = '
.font-sampler-container {
    margin: 20px 0;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
}

.font-sampler-preview {
    margin-bottom: 20px;
    padding: 20px;
    background: white;
    border-radius: 4px;
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.font-sample {
    text-align: center;
    line-height: 1.2;
    word-wrap: break-word;
    max-width: 100%;
}

.font-sampler-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.control-group label {
    font-weight: bold;
    font-size: 14px;
}

.control-group input[type="range"] {
    width: 100%;
}

.control-group input[type="text"] {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.size-value,
.weight-value,
.width-value {
    font-size: 12px;
    color: #666;
    text-align: center;
}

.font-input-group {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}

.font-input-group input {
    flex: 1;
    padding: 8px;
}

.usage-info {
    background: #f0f8ff;
    padding: 15px;
    border-left: 4px solid #0073aa;
    margin-bottom: 20px;
}

.usage-info code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
}

@media (max-width: 768px) {
    .font-sampler-controls {
        grid-template-columns: 1fr;
    }
    
    .font-input-group {
        flex-direction: column;
        align-items: stretch;
    }
}
        ';
        
        file_put_contents($plugin_upload_dir . 'font-sampler.css', $css_content);
    }
    
    private function varifosa_create_js_file() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        $js_content = '
jQuery(document).ready(function($) {
    // Initialize font samplers
    $(".font-sampler-container").each(function() {
        var container = $(this);
        var sample = container.find(".font-sample");
        var fontUrl = sample.data("font");
        
        if (fontUrl) {
            loadFont(fontUrl, sample);
        }
        
        // Size control
        container.find(".size-control").on("input", function() {
            var size = $(this).val();
            sample.css("font-size", size + "px");
            container.find(".size-value").text(size + "px");
        });
        
        // Weight control
        container.find(".weight-control").on("input", function() {
            var weight = $(this).val();
            var width = container.find(".width-control").val() || 100;
            
            // Combine both weight and width in font-variation-settings
            sample.css({
                "font-weight": weight,
                "font-variation-settings": "\\"wght\\" " + weight + ", \\"wdth\\" " + width
            });
            container.find(".weight-value").text(weight);
        });
        
        // Width control (using font-stretch and font-variation-settings)
        container.find(".width-control").on("input", function() {
            var width = $(this).val();
            var weight = container.find(".weight-control").val() || 400;
            
            // Combine both weight and width in font-variation-settings
            sample.css({
                "font-stretch": width + "%",
                "font-variation-settings": "\\"wght\\" " + weight + ", \\"wdth\\" " + width
            });
            container.find(".width-value").text(width + "%");
        });
        
        // Text control
        container.find(".text-control").on("input", function() {
            var text = $(this).val();
            sample.text(text);
        });
    });
    
    function loadFont(url, element) {
        // Check if FontFace API is supported
        if (!window.FontFace) {
            // Fallback for older browsers
            loadFontFallback(url, element);
            return;
        }
        
        var fontFace = new FontFace("VariableFont-" + Date.now(), "url(" + url + ")");
        
        fontFace.load().then(function(loadedFont) {
            document.fonts.add(loadedFont);
            element.css({
                "font-family": loadedFont.family + ", sans-serif",
                "font-variation-settings": "\\"wght\\" 400, \\"wdth\\" 100"
            });
            
            // Initialize with proper font-variation-settings
            var container = element.closest(\'.font-sampler-container\');
            var initialWeight = container.find(\'.weight-control\').val() || 400;
            var initialWidth = container.find(\'.width-control\').val() || 100;
            
            element.css("font-variation-settings", "\\"wght\\" " + initialWeight + ", \\"wdth\\" " + initialWidth);
            
        }).catch(function(error) {
            console.error("Font loading failed:", error);
            loadFontFallback(url, element);
        });
    }
    
    function loadFontFallback(url, element) {
        // Fallback method using CSS @font-face
        var fontFamily = "VariableFont-" + Date.now();
        var style = document.createElement("style");
        style.textContent = "@font-face { font-family: \\"" + fontFamily + "\\"; src: url(\\"" + url + "\\"); font-display: swap; }";
        document.head.appendChild(style);
        
        element.css({
            "font-family": fontFamily + ", sans-serif"
        });
        
        // Add a small delay to ensure font is loaded
        setTimeout(function() {
            var container = element.closest(\'.font-sampler-container\');
            var initialWeight = container.find(\'.weight-control\').val() || 400;
            var initialWidth = container.find(\'.width-control\').val() || 100;
            
            element.css({
                "font-weight": initialWeight,
                "font-stretch": initialWidth + "%",
                "font-variation-settings": "\\"wght\\" " + initialWeight + ", \\"wdth\\" " + initialWidth
            });
        }, 100);
    }
});
        ';
        
        file_put_contents($plugin_upload_dir . 'font-sampler.js', $js_content);
    }
    
    private function varifosa_create_admin_js_file() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        $admin_js_content = '
jQuery(document).ready(function($) {
    var mediaUploader;
    
    // Font upload button
    $(".upload-font-btn").on("click", function(e) {
        e.preventDefault();
        
        var button = $(this);
        var input = button.prev("input");
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: "Choose Font File",
            button: {
                text: "Use this font"
            },
            multiple: false,
            library: {
                type: ["application/font-woff", "application/font-woff2", "font/woff", "font/woff2", "application/x-font-ttf"]
            }
        });
        
        mediaUploader.on("select", function() {
            var attachment = mediaUploader.state().get("selection").first().toJSON();
            input.val(attachment.url);
        });
        
        mediaUploader.open();
    });
    
    // Add font button
    var fontIndex = $("#custom-fonts-container .font-input-group").length;
    
    $(".add-font-btn").on("click", function() {
        var newFontGroup = `
            <div class="font-input-group">
                <input type="text" name="varifosa_custom_fonts[${fontIndex}][name]" 
                       placeholder="Font Name" />
                <input type="url" name="varifosa_custom_fonts[${fontIndex}][url]" 
                       placeholder="Font URL" />
                <button type="button" class="button remove-font-btn">Remove</button>
            </div>
        `;
        
        $("#custom-fonts-container").append(newFontGroup);
        fontIndex++;
    });
    
    // Remove font button
    $(document).on("click", ".remove-font-btn", function() {
        $(this).closest(".font-input-group").remove();
    });
});
        ';
        
        file_put_contents($plugin_upload_dir . 'admin.js', $admin_js_content);
    }
}

// Initialize the plugin
new Varifosa_Sampler();

?>
