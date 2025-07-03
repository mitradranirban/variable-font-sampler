=== Variable Font Sampler ===
Contributors: mitradranirban
Tags: fonts, font preview, variable font, font foundry. fontsampler
Requires at least: 5.7
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.4
License: GPLv3 or later 
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Show your variable font in your wordpress site with user determined preview text and slider for weight, width, and font size 

== Demo site ==

<a href=https://fontsampler.atipra.in>Fontsampler Demo Site</a> 

== Plugin site ==

Visit the <a href="https://github.com/mitradranirban/variable-font-sampler">plugin GitHub page</a>

== Key Features ==

1.  <b>Shortcode Support</b>: Use `[font_sampler]` with customizable parameters
    
2.  <b>Interactive Controls</b>: Sliders for font size, weight, width, and text input
    
3.  <b>Admin Panel</b>: Settings page to configure default fonts and manage custom fonts
    
4.  <b>Font Upload</b>: Media library integration for uploading font files
    
5.  <b>Responsive Design</b>: Mobile-friendly interface
    
6.  <b>Variable Font Support</b>: Specifically designed for variable fonts with multiple axes
    

== Usage: ==

= Basic Shortcode: =

`[font_sampler font="https://example.com/font.woff2"]`

= Advanced Shortcode: =

`[font_sampler font="https://example.com/font.woff2" text="Custom sample text" size="48" controls="true"]` 


== Features of the Plugin ==

*   <b>Font Loading</b>: Uses the Font Loading API for reliable font loading
    
*   <b>Variable Font Controls</b>: Interactive sliders for weight, width, and size
    
*   <b>Custom Text</b>: Users can change the sample text in real-time
    
*   <b>Admin Interface</b>: Easy-to-use settings panel for managing fonts
    
*   <b>Error Handling</b>: Graceful fallbacks when fonts fail to load
    
*   <b>Responsive</b>: Works well on all device sizes

== Installation ==

= Install process is quite simple : =

– After getting plugin ZIP file log onto WP admin page.
– Open Plugins >> Add new.
– Click on “Upload plugin” beside top heading.
– Drag and drop plugin zip file.
- Press Install button 
- Activate the plugin 

== Changelog ==
= 1.0.4 (03 July 2025)
* Removes all "default font" and "additional fonts" settings and code.
* Admin interface now only provides usage instructions and a font upload (to the Media Library) button,   displaying the uploaded font URL for the shortcode.
* Shortcode requires the font attribute; no fallback to defaults.
* JavaScript only manages a single font upload.
= 1.0.3 (3 July 2025) =
* Added Version tag to bust browser cache 
= 1.0.2 (03 July 2025)
* requires php bumped to 7.0 (issue#1)
* load_plugin_textdomain removed (issue#2)
* generic functions prefixed with varifosa_(issue#3)
* files uploaded to uploads folder instead of plugin folder and linked from there (issue#4)
= 1.0.1 (18 June 2025)=
* Corrected escape errors 
= 1.0.0 (17 June 2025) =
* First release 

