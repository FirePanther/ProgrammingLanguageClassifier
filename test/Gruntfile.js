module.exports = grunt => {
	grunt.loadNpmTasks('grunt-shell');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-sass');
	
	grunt.initConfig({
		shell: {
			concat: {
				command: 'php -f concat.php'
			}
		},
		sass: {
			dist: {
				options: {
					style: 'compact',
					sourcemap: 'none'
				},
				files: {
					'src/style.css': 'src/style.scss'
				}
			}
		},
		// just for development
		watch: {
			options: {
				interrupt: true
			},
			php: {
				files: 'src/*.php',
				tasks: 'shell:concatDev'
			},
			scss: {
				files: 'src/*.scss',
				tasks: ['sass', 'shell:concatDev'],
			},
			html : {
				files: 'src/html/*.html',
				tasks: 'shell:concatDev'
			},
			icons : {
				files: 'src/icons/*.svg',
				tasks: 'shell:concatDev'
			}
		}
	});
	
	grunt.registerTask('default', ['sass', 'shell:concat']);
};