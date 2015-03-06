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
        'css/syn-bw.min.css': 'sass/main-bw.scss',
        'css/minimal.min.css': 'sass/minimal.scss',
        'css/minimal-bw.min.css': 'sass/minimal-bw.scss',
        'css/awesome.min.css': 'sass/main-awesome.scss',
        'css/awesome-bw.min.css': 'sass/main-awesome-bw.scss',
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

  // Default task(s).
  grunt.registerTask('default', ['wp_readme_to_markdown', 'makepot', 'sass']);
};
