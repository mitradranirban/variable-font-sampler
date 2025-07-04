<?php
/**
 * Plugin Name: Variable Font Sampler
 * Plugin URI: https://github.com/mitradranirban/variable-font-sampler/blob/main/variable-font-sampler.php
 * Description: A WordPress plugin for showcasing variable fonts with interactive controls.
 * Version: 1.0.4
 * Author: Dr Anirban Mitra
 * Author URI: https://fonts.atipra.in
 * License: GPL v3 or later
 * Text Domain: variable-font-sampler
 * Updated: 2025-07-03 14:19:48
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Varifosa_Sampler {
    
    private $plugin_url;
    private $plugin_path;
    private $plugin_upload_dir;
    private $plugin_upload_url;
    
    // Plugin version constant for cache busting
    const VERSION = '1.0.4';
    
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
        add_shortcode('font_sampler', array($this, 'varifosa_font_sampler_shortcode'));
        
        register_activation_hook(__FILE__, array($this, 'varifosa_activate'));
        register_deactivation_hook(__FILE__, array($this, 'varifosa_deactivate'));
    }
    
    public function varifosa_init() {
        // Initialize plugin
        load_plugin_textdomain('variable-font-sampler', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
            self::VERSION
        );
        wp_enqueue_script(
            'font-sampler',
            $plugin_upload_url . 'font-sampler.js',
            array('jquery'),
            self::VERSION,
            true
        );
        
        // Localize script with translations
        wp_localize_script('font-sampler', 'fontSampler', array(
            'i18n' => array(
                'fontLoadError' => esc_html__('Failed to load the font. Please check if the font file is accessible.', 'variable-font-sampler'),
                'invalidFont' => esc_html__('Invalid font file. Please upload a valid variable font.', 'variable-font-sampler')
            )
        ));
    }
    
    public function varifosa_font_sampler_shortcode($atts) {
        $atts = shortcode_atts(array(
            'font' => '',
            'text' => 'The quick brown fox jumps over the lazy dog',
            'size' => '32',
            'controls' => 'true',
            'id' => uniqid('font-sampler-')
        ), $atts);
        
        // Validate font URL
        if (!empty($atts['font'])) {
            $font_url = esc_url($atts['font'], array('http', 'https'));
            if (empty($font_url)) {
                return sprintf(
                    '<div class="font-error">%s</div>',
                    esc_html__('Invalid font URL provided.', 'variable-font-sampler')
                );
            }
        } else {
            return sprintf(
                '<div class="font-error">%s</div>',
                esc_html__('No font URL specified.', 'variable-font-sampler')
            );
        }
        
        // Sanitize size
        $size = absint($atts['size']);
        if ($size < 12 || $size > 120) {
            $size = 32; // Default if outside valid range
        }
        
        ob_start();
        ?>
        <div class="font-sampler-container" id="<?php echo esc_attr($atts['id']); ?>">
            <div class="font-sampler-preview">
                <div class="font-sample" 
                     data-font="<?php echo esc_url($font_url); ?>"
                     data-text="<?php echo esc_attr($atts['text']); ?>"
                     data-size="<?php echo esc_attr($size); ?>"
                     data-controls="<?php echo esc_attr($atts['controls']); ?>">
                    <?php echo esc_html($atts['text']); ?>
                </div>
            </div>
            
            <?php if ($atts['controls'] === 'true'): ?>
            <div class="font-sampler-controls">
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-size"><?php esc_html_e('Font Size:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-size" 
                           class="size-control" min="12" max="120" value="<?php echo esc_attr($size); ?>">
                    <span class="size-value"><?php echo esc_html($size); ?>px</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-weight"><?php esc_html_e('Font Weight:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-weight" 
                           class="weight-control" min="100" max="900" value="400" step="100">
                    <span class="weight-value">400</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-width"><?php esc_html_e('Font Width:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-width" 
                           class="width-control" min="50" max="200" value="100">
                    <span class="width-value">100%</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-text"><?php esc_html_e('Sample Text:', 'variable-font-sampler'); ?></label>
                    <input type="text" id="<?php echo esc_attr($atts['id']); ?>-text" 
                           class="text-control" value="<?php echo esc_attr($atts['text']); ?>">
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
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

.font-error {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    text-align: center;
}

@media (max-width: 768px) {
    .font-sampler-controls {
        grid-template-columns: 1fr;
    }
}';
        
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
            loadFont(fontUrl, sample).catch(function(error) {
                console.error("Font loading failed:", error);
                sample.before(\'<div class="font-error">\' + 
                    fontSampler.i18n.fontLoadError + 
                    \'</div>\');
            });
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
            
            sample.css({
                "font-weight": weight,
                "font-variation-settings": "\\"wght\\" " + weight + ", \\"wdth\\" " + width
            });
            container.find(".weight-value").text(weight);
        });
        
        // Width control
        container.find(".width-control").on("input", function() {
            var width = $(this).val();
            var weight = container.find(".weight-control").val() || 400;
            
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
    
    async function loadFont(url, element) {
        try {
            if (!window.FontFace) {
                throw new Error("FontFace API not supported");
            }
            
            const fontFace = new FontFace("VariableFont-" + Date.now(), "url(" + url + ")");
            const loadedFont = await fontFace.load();
            document.fonts.add(loadedFont);
            
            element.css({
                "font-family": loadedFont.family + ", sans-serif",
                "font-variation-settings": "\\"wght\\" 400, \\"wdth\\" 100"
            });
            
            const container = element.closest(".font-sampler-container");
            const initialWeight = container.find(".weight-control").val() || 400;
            const initialWidth = container.find(".width-control").val() || 100;
            
            element.css("font-variation-settings", 
                "\\"wght\\" " + initialWeight + ", \\"wdth\\" " + initialWidth);
                
        } catch (error) {
            await loadFontFallback(url, element);
        }
    }
    
    function loadFontFallback(url, element) {
        return new Promise((resolve, reject) => {
            const fontFamily = "VariableFont-" + Date.now();
            const style = document.createElement("style");
            style.textContent = 
                "@font-face { " +
                "font-family: \\"" + fontFamily + "\\"; " +
                "src: url(\\"" + url + "\\"); " +
                "font-display: swap; " +
                "}";
            document.head.appendChild(style);
            
            element.css({
                "font-family": fontFamily + ", sans-serif"
            });
            
            setTimeout(function() {
                const container = element.closest(".font-sampler-container");
                const initialWeight = container.find(".weight-control").val() || 400;
                const initialWidth = container.find(".width-control").val() || 100;
                
                element.css({
                    "font-weight": initialWeight,
                    "font-stretch": initialWidth + "%",
                    "font-variation-settings": 
                        "\\"wght\\" " + initialWeight + ", \\"wdth\\" " + initialWidth
                });
                resolve();
            }, 100);
        });
    }
});';
        
        file_put_contents($plugin_upload_dir . 'font-sampler.js', $js_content);
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
    }
    
    public function varifosa_deactivate() {
        // Cleanup tasks if needed
    }
}

// Initialize the plugin
new Varifosa_Sampler();
?>
