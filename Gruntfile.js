module.exports = function(grunt) {
  // Project configuration.
  grunt.initConfig({
	      execute: {
		target: {
			src: ['simpleicons.js']
	          }
	      },
              checktextdomain: {
                        options:{
                                text_domain: 'syndication-links',
                                keywords: [
                                        '__:1,2d',
                                        '_e:1,2d',
                                        '_x:1,2c,3d',
                                        'esc_html__:1,2d',
                                        'esc_html_e:1,2d',
                                        'esc_html_x:1,2c,3d',
                                        'esc_attr__:1,2d',
                                        'esc_attr_e:1,2d',
                                        'esc_attr_x:1,2c,3d',
                                        '_ex:1,2c,3d',
                                        '_n:1,2,4d',
                                        '_nx:1,2,4c,5d',
                                        '_n_noop:1,2,3d',
                                        '_nx_noop:1,2,3c,4d'
                                ]
                        },
                        files: {
                                src:  [
                                        '**/*.php',         // Include all files
                                        'includes/*.php', // Include includes
                                        '!sass/**',       // Exclude sass/
                                        '!node_modules/**', // Exclude node_modules/
                                        '!tests/**',        // Exclude tests/
                                        '!vendor/**',       // Exclude vendor/
                                        '!build/**'           // Exclude build/
                                ],
                                expand: true
                        }
                },


    wp_readme_to_markdown: {
      target: {
       options: {
          screenshot_url: '/assets/{screenshot}.png'
       },
        files: {
          'readme.md': 'readme.txt'
        }
      }
     },
    sass: {                              // Task
       dev: {                            // Target
         options: {                       // Target options
             style: 'expanded'
             },
          files: {                         // Dictionary of files
        'css/syn.css': 'sass/main.scss',       // 'destination': 'source'
         }
	},
       dist: {                            // Target
         options: {                       // Target options
             style: 'compressed'
             },
          files: {                         // Dictionary of files
        'css/syn.min.css': 'sass/main.scss',       // 'destination': 'source'
	'css/syn-medium.min.css': 'sass/main-medium.scss',
	'css/syn-large.min.css': 'sass/main-large.scss',
        'css/syn-bw.min.css': 'sass/main-bw.scss',
	'css/syn-bw-medium.min.css': 'sass/main-bw-medium.scss',
	'css/syn-bw-large.min.css': 'sass/main-bw-large.scss',
         }
	}
  },

        svgstore: {
                options: {
                        prefix : '', // Unused by us, but svgstore demands this variable
                        cleanup : ['style', 'fill', 'id'],
                        svg: { // will add and overide the the default xmlns="http://www.w3.org/2000/svg" attribute to the resulting SVG
                                viewBox : '0 0 24 24',
                                xmlns: 'http://www.w3.org/2000/svg'
                        },
                },
                dist: {
                                files: {
                                        'icons.svg': ['node_modules/simple-icons/icons/*.svg', 'node_modules/genericons-neue/svg/*.svg']
                                }
                }
        },


 copy: {
           main: {
               options: {
                   mode: true
               },
               src: [
                   '**',
                   '!node_modules/**',
                   '!build/**',
                   '!.git/**',
                   '!Gruntfile.js',
                   '!package.json',
                   '!.gitignore',
                   '!sass/.sass-cache/**',
		   '!vendor/**',
       '!syn.css.map',
       '!syn.min.css.map'
               ],
               dest: 'build/trunk/'
           },
        assets: {
               options: {
                   mode: true
               },
               src: [
                 'assets/*'
               ],
               dest: 'build/'
            }

       },


   makepot: {
        target: {
            options: {
		mainFile: 'syndication-links.php', // Main project file.
                domainPath: '/languages',                   // Where to save the POT file.
                potFilename: 'syndication-links.pot',
		exclude: ['build/.*'],
                type: 'wp-plugin',                // Type of project (wp-plugin or wp-theme).
                updateTimestamp: true             // Whether the POT-Creation-Date should be updated without other changes.
            	}
            }
      }
  });

  grunt.loadNpmTasks('grunt-wp-readme-to-markdown');
  grunt.loadNpmTasks( 'grunt-wp-i18n' );
  grunt.loadNpmTasks('grunt-contrib-sass');
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-svgstore');
  grunt.loadNpmTasks('grunt-checktextdomain');
  grunt.loadNpmTasks('grunt-execute');

  // Default task(s).
  grunt.registerTask('default', ['wp_readme_to_markdown', 'makepot', 'sass', 'checktextdomain']);
};
