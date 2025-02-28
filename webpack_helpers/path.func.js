const fs = require('fs');

const removeDir = (path) => {
	if (fs.existsSync(path)) {
		const files = fs.readdirSync(path);
		if (files.length > 0) {
			files.forEach(function(filename) {
				if (fs.statSync(path + "/" + filename).isDirectory()) {
					removeDir(path + "/" + filename);
				} else {
					fs.unlinkSync(path + "/" + filename);
				}
			});
			fs.rmdirSync(path);
		} else {
			fs.rmdirSync(path);
		}
	} else {
		console.log("Directory path not found...")
	}
};

module.exports = {
	pathDepth: function (path, separator) {
		return path.split(separator).length;
	},
	removeDir: removeDir
};
