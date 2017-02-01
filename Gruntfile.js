module.exports = function(grunt) {
  // Project configuration.
  grunt.initConfig({
    wp_readme_to_markdown: {
      target: {
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
                                        'includes/social-logos.svg': ['svgs/*.svg']
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
       '!syn.css.map',
       '!syn.min.css.map'
               ],
               dest: 'build/trunk/'
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

  // Default task(s).
  grunt.registerTask('default', ['wp_readme_to_markdown', 'makepot', 'sass', 'svgstore']);
};
