module.exports = function(grunt) {
  // Project configuration.
  grunt.initConfig({
    wp_readme_to_markdown: {
      target: {
        files: {
          'README.md': 'readme.txt'
        }
      }
     },
    sass: {                              // Task
       dev: {                            // Target
         options: {                       // Target options
             style: 'expanded'
             },
          files: {                         // Dictionary of files
        'syn.css': 'sass/main.scss',       // 'destination': 'source'
         }
	},
       dist: {                            // Target
         options: {                       // Target options
             style: 'compressed'
             },
          files: {                         // Dictionary of files
        'syn.min.css': 'sass/main.scss',       // 'destination': 'source'
         }
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

  // Default task(s).
  grunt.registerTask('default', ['wp_readme_to_markdown', 'makepot', 'sass']);
};
