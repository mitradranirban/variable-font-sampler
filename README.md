   

# Variable Font Sampler - a WordPress plugin for font sampling using the fontsampler.js library

## Key Features:

1.  **Shortcode Support**: Use `[font_sampler]` with customizable parameters
    
2.  **Interactive Controls**: Sliders for font size, weight, width, and text input
    
3.  **Admin Panel**: Settings page to configure default fonts and manage custom fonts
    
4.  **Font Upload**: Media library integration for uploading font files
    
5.  **Responsive Design**: Mobile-friendly interface
    
6.  **Variable Font Support**: Specifically designed for variable fonts with multiple axes
    

## Usage:

### Basic Shortcode:

```
[font_sampler font="https://example.com/font.woff2"]
```

### Advanced Shortcode:

```
[font_sampler font="https://example.com/font.woff2" text="Custom sample text" size="48" controls="true"]
```

## Installation Instructions:

1.  Create a new folder called `variable-font-sampler` in your `/wp-content/plugins/` directory
    
2.  Save the code as `variable-font-sampler.php` in that folder
    
3.  Activate the plugin from your WordPress admin panel
    
4.  Go to Settings → Font Sampler to configure default fonts
    

## Features of the Plugin:

*   **Font Loading**: Uses the Font Loading API for reliable font loading
    
*   **Variable Font Controls**: Interactive sliders for weight, width, and size
    
*   **Custom Text**: Users can change the sample text in real-time
    
*   **Admin Interface**: Easy-to-use settings panel for managing fonts
    
*   **Error Handling**: Graceful fallbacks when fonts fail to load
    
*   **Responsive**: Works well on all device sizes
