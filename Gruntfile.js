module.exports = function(grunt) {

    grunt.initConfig({
        compress: {
            main: {
                options: {
                    archive: 'modselfupdate.zip'
                },
                files: [
                    {src: ['controllers/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['classes/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['docs/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['override/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['logs/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['vendor/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['translations/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['upgrade/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['optionaloverride/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['oldoverride/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['sql/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['lib/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['defaultoverride/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: ['views/**'], dest: 'modselfupdate/', filter: 'isFile'},
                    {src: 'config.xml', dest: 'modselfupdate/'},
                    {src: 'index.php', dest: 'modselfupdate/'},
                    {src: 'modselfupdate.php', dest: 'modselfupdate/'},
                    {src: 'logo.png', dest: 'modselfupdate/'},
                    {src: 'logo.gif', dest: 'modselfupdate/'},
                    {src: 'LICENSE', dest: 'modselfupdate/'}
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('default', ['compress']);
};