const groupDataByPrefixKey = (data, separator, withoutHead = true) => {
	/*
		Below commented are previous version.
		The result is not i wanted
	*/
	// const dataKeys = Object.keys(data);
	// const output =  dataKeys.reduce((result, currKey) => {
	// 	// Pull out the group name from the key
	// 	const group = currKey.split(separator)[0];
	// 	// Check if the group exists, if not, create it
	// 	const hasGroup = result[group] !== undefined;
	// 	if (!hasGroup)
	// 	result[group] = {};
	// 	// Add the current entry to the result
	// 	result[group][currKey] = data[currKey];
	// 	return result;
	// }, {});
	// return output;

	let result = {};
	let dataKeys = Object.keys(data);
	let groupKeys = {};
	dataKeys.forEach((v,i) => {
		const _prefix = v.split(separator)[0];
		groupKeys[_prefix] = {
			total: (groupKeys.hasOwnProperty(_prefix)) ? groupKeys[_prefix]['total'] + 1 : 1,
			alias: (groupKeys.hasOwnProperty(_prefix)) ? [ ...groupKeys[_prefix]['alias'], v ] : [ v ]
		};
	});

	for (var groupName in groupKeys) {
        if (groupKeys[groupName]['total'] >= 2) {
            result[groupName] = {};
            groupKeys[groupName]['alias'].forEach((v,i) => {
                //console.log(data[v]);
                const headItem = (withoutHead == true) ? v.replace(groupName+'_','') : v;
                result[groupName][headItem] = data[v];
            });
        } else {
            result[groupKeys[groupName]['alias'][0]] = data[groupKeys[groupName]['alias'][0]];
        }
	}

    return result;
}

module.exports = {
	groupDataByPrefixKey: groupDataByPrefixKey
};
