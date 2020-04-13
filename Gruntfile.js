module.exports = function (grunt) {
	// Project configuration.
	grunt.initConfig(
		{
		       eslint: {
		       	 options: {
			    		fix: true
				   },
			 synlinks: {
					src: ['js/synlinks.js' ]
			 }
			},
			execute              : {
				target: {
					src: ['simpleicons.js']
				}
			},
			checktextdomain      : {
				options: {
					text_domain: 'syndication-links',
					keywords   : [
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
				files  : {
					src   : [
						'**/*.php',         // Include all files.
						'includes/*.php',   // Include includes.
						'!sass/**',         // Exclude sass/.
						'!node_modules/**', // Exclude node_modules/.
						'!tests/**',        // Exclude tests/.
						'!vendor/**',       // Exclude vendor/.
						'!build/**'         // Exclude build/.
					],
					expand: true
				}
			},
			copy                 : {
				main: {
					files: [
						{expand: true, cwd: 'node_modules/simple-icons/icons/', src: ['*.svg'], dest: 'svgs/'},
						{expand: true, cwd: 'node_modules/genericons-neue/svg-min/', src: ['*.svg'], dest: 'svgs/'},
					],
				},
			},
			wp_readme_to_markdown: {
				target: {
					options: {
						screenshot_url: '/assets/{screenshot}.png'
					},
					files  : {
						'readme.md': 'readme.txt'
					}
				}
			},
			sass                 : { // Task.
				dev : { // Target.
					options: { // Target options.
						style: 'expanded'
					},
					files  : { // Dictionary of files.
						'css/syn.css': 'sass/main.scss', // 'destination': 'source'
					}
				},
				dist: { // Target.
					options: { // Target options.
						style: 'compressed'
					},
					files  : { // Dictionary of files.
						'css/syn.min.css'          : 'sass/main.scss', // 'destination': 'source'
						'css/syn-medium.min.css'   : 'sass/main-medium.scss',
						'css/syn-large.min.css'    : 'sass/main-large.scss',
						'css/syn-bw.min.css'       : 'sass/main-bw.scss',
						'css/syn-bw-medium.min.css': 'sass/main-bw-medium.scss',
						'css/syn-bw-large.min.css' : 'sass/main-bw-large.scss',
					}
				}
			}
		}
	);

	grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown' );
	grunt.loadNpmTasks( 'grunt-contrib-sass' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-checktextdomain' );
	grunt.loadNpmTasks( 'grunt-execute' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );

	// Default task(s).
	grunt.registerTask( 'default', ['wp_readme_to_markdown', 'eslint', 'copy', 'sass', 'checktextdomain'] );
};
