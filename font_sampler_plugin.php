<?php
/**
 * Plugin Name: Variable Font Sampler
 * Plugin URI: https://yourwebsite.com/
 * Description: A WordPress plugin for showcasing variable fonts using fontsampler.js library with interactive controls.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: variable-font-sampler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VariableFontSampler {
    
    private $plugin_url;
    private $plugin_path;
    
    public function __construct() {
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_shortcode('font_sampler', array($this, 'font_sampler_shortcode'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        load_plugin_textdomain('variable-font-sampler', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function enqueue_scripts() {
        // Enqueue fontsampler.js from CDN
        wp_enqueue_script(
            'fontsampler-js',
            'https://cdnjs.cloudflare.com/ajax/libs/fontsampler/1.0.0/fontsampler.min.js',
            array(),
            '1.0.0',
            true
        );
        
        // Enqueue our custom script
        wp_enqueue_script(
            'variable-font-sampler',
            $this->plugin_url . 'assets/js/font-sampler.js',
            array('jquery', 'fontsampler-js'),
            '1.0.0',
            true
        );
        
        // Enqueue CSS
        wp_enqueue_style(
            'variable-font-sampler-css',
            $this->plugin_url . 'assets/css/font-sampler.css',
            array(),
            '1.0.0'
        );
        
        // Localize script for AJAX
        wp_localize_script('variable-font-sampler', 'fontSampler', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('font_sampler_nonce')
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        if ('settings_page_variable-font-sampler' !== $hook) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script(
            'variable-font-sampler-admin',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
    }
    
    public function font_sampler_shortcode($atts) {
        $atts = shortcode_atts(array(
            'font' => '',
            'text' => 'The quick brown fox jumps over the lazy dog',
            'size' => '32',
            'controls' => 'true',
            'id' => uniqid('font-sampler-')
        ), $atts);
        
        $font_url = $atts['font'] ? $atts['font'] : get_option('vfs_default_font', '');
        
        if (empty($font_url)) {
            return '<p>' . __('No font specified. Please add a font URL or set a default font in the plugin settings.', 'variable-font-sampler') . '</p>';
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
                    <label for="<?php echo esc_attr($atts['id']); ?>-size"><?php _e('Font Size:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-size" 
                           class="size-control" min="12" max="120" value="<?php echo esc_attr($atts['size']); ?>">
                    <span class="size-value"><?php echo esc_html($atts['size']); ?>px</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-weight"><?php _e('Font Weight:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-weight" 
                           class="weight-control" min="100" max="900" value="400" step="100">
                    <span class="weight-value">400</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-width"><?php _e('Font Width:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-width" 
                           class="width-control" min="50" max="200" value="100">
                    <span class="width-value">100%</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-text"><?php _e('Sample Text:', 'variable-font-sampler'); ?></label>
                    <input type="text" id="<?php echo esc_attr($atts['id']); ?>-text" 
                           class="text-control" value="<?php echo esc_attr($atts['text']); ?>">
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Variable Font Sampler', 'variable-font-sampler'),
            __('Font Sampler', 'variable-font-sampler'),
            'manage_options',
            'variable-font-sampler',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        register_setting('vfs_settings', 'vfs_default_font');
        register_setting('vfs_settings', 'vfs_custom_fonts');
        
        add_settings_section(
            'vfs_main_section',
            __('Font Settings', 'variable-font-sampler'),
            array($this, 'settings_section_callback'),
            'variable-font-sampler'
        );
        
        add_settings_field(
            'vfs_default_font',
            __('Default Font URL', 'variable-font-sampler'),
            array($this, 'default_font_callback'),
            'variable-font-sampler',
            'vfs_main_section'
        );
        
        add_settings_field(
            'vfs_custom_fonts',
            __('Custom Fonts', 'variable-font-sampler'),
            array($this, 'custom_fonts_callback'),
            'variable-font-sampler',
            'vfs_main_section'
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Variable Font Sampler Settings', 'variable-font-sampler'); ?></h1>
            
            <div class="usage-info">
                <h3><?php _e('Usage Instructions', 'variable-font-sampler'); ?></h3>
                <p><?php _e('Use the shortcode to display font samples:', 'variable-font-sampler'); ?></p>
                <code>[font_sampler font="URL_TO_FONT" text="Sample text" size="32" controls="true"]</code>
                <p><?php _e('Parameters:', 'variable-font-sampler'); ?></p>
                <ul>
                    <li><strong>font:</strong> <?php _e('URL to the variable font file (optional if default is set)', 'variable-font-sampler'); ?></li>
                    <li><strong>text:</strong> <?php _e('Sample text to display (default: "The quick brown fox...")', 'variable-font-sampler'); ?></li>
                    <li><strong>size:</strong> <?php _e('Initial font size in pixels (default: 32)', 'variable-font-sampler'); ?></li>
                    <li><strong>controls:</strong> <?php _e('Show interactive controls (default: true)', 'variable-font-sampler'); ?></li>
                </ul>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('vfs_settings');
                do_settings_sections('variable-font-sampler');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function settings_section_callback() {
        echo '<p>' . __('Configure your variable font settings below.', 'variable-font-sampler') . '</p>';
    }
    
    public function default_font_callback() {
        $value = get_option('vfs_default_font', '');
        echo '<input type="url" name="vfs_default_font" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<button type="button" class="button upload-font-btn">' . __('Upload Font', 'variable-font-sampler') . '</button>';
        echo '<p class="description">' . __('Enter the URL to your default variable font file (.woff2, .woff, .ttf)', 'variable-font-sampler') . '</p>';
    }
    
    public function custom_fonts_callback() {
        $fonts = get_option('vfs_custom_fonts', array());
        echo '<div id="custom-fonts-container">';
        
        if (!empty($fonts)) {
            foreach ($fonts as $index => $font) {
                $this->render_font_input($index, $font);
            }
        } else {
            $this->render_font_input(0, array('name' => '', 'url' => ''));
        }
        
        echo '</div>';
        echo '<button type="button" class="button add-font-btn">' . __('Add Another Font', 'variable-font-sampler') . '</button>';
    }
    
    private function render_font_input($index, $font) {
        ?>
        <div class="font-input-group">
            <input type="text" name="vfs_custom_fonts[<?php echo $index; ?>][name]" 
                   value="<?php echo esc_attr($font['name']); ?>" 
                   placeholder="<?php _e('Font Name', 'variable-font-sampler'); ?>" />
            <input type="url" name="vfs_custom_fonts[<?php echo $index; ?>][url]" 
                   value="<?php echo esc_attr($font['url']); ?>" 
                   placeholder="<?php _e('Font URL', 'variable-font-sampler'); ?>" />
            <button type="button" class="button remove-font-btn"><?php _e('Remove', 'variable-font-sampler'); ?></button>
        </div>
        <?php
    }
    
    public function activate() {
        // Create assets directory structure
        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/variable-fonts/';
        
        if (!file_exists($font_dir)) {
            wp_mkdir_p($font_dir);
        }
        
        // Create CSS file
        $this->create_css_file();
        
        // Create JS file
        $this->create_js_file();
        
        // Create admin JS file
        $this->create_admin_js_file();
    }
    
    public function deactivate() {
        // Clean up if needed
    }
    
    private function create_css_file() {
        $css_dir = $this->plugin_path . 'assets/css/';
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
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
        
        file_put_contents($css_dir . 'font-sampler.css', $css_content);
    }
    
    private function create_js_file() {
        $js_dir = $this->plugin_path . 'assets/js/';
        if (!file_exists($js_dir)) {
            wp_mkdir_p($js_dir);
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
            sample.css("font-weight", weight);
            container.find(".weight-value").text(weight);
        });
        
        // Width control
        container.find(".width-control").on("input", function() {
            var width = $(this).val();
            sample.css("font-stretch", width + "%");
            container.find(".width-value").text(width + "%");
        });
        
        // Text control
        container.find(".text-control").on("input", function() {
            var text = $(this).val();
            sample.text(text);
        });
    });
    
    function loadFont(url, element) {
        var fontFace = new FontFace("VariableFont", "url(" + url + ")");
        
        fontFace.load().then(function(loadedFont) {
            document.fonts.add(loadedFont);
            element.css("font-family", "VariableFont, sans-serif");
        }).catch(function(error) {
            console.error("Font loading failed:", error);
            element.before("<p style=\"color: red; font-size: 14px;\">Failed to load font: " + url + "</p>");
        });
    }
});
        ';
        
        file_put_contents($js_dir . 'font-sampler.js', $js_content);
    }
    
    private function create_admin_js_file() {
        $js_dir = $this->plugin_path . 'assets/js/';
        if (!file_exists($js_dir)) {
            wp_mkdir_p($js_dir);
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
                <input type="text" name="vfs_custom_fonts[${fontIndex}][name]" 
                       placeholder="Font Name" />
                <input type="url" name="vfs_custom_fonts[${fontIndex}][url]" 
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
        
        file_put_contents($js_dir . 'admin.js', $admin_js_content);
    }
}

// Initialize the plugin
new VariableFontSampler();

?>