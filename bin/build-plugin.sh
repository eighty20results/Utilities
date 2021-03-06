#!/usr/bin/env bash
#
# Build script for Eighty/20 Results WordPress plugins
#
# Copyright 2014 - 2021 (c) Eighty / 20 Results by Wicked Strong Chicks, LLC
#
short_name="${1}"
remote_server="eighty20results.com"
declare -a include=( \
	"docs" \
	"inc" \
	"src" \
	"class-loader.php" \
	"README.txt" \
	"CHANGELOG.md"
	)
declare -a exclude=( \
	".git" \
	"docker" \
	"bin" \
	"Dockerfile" \
	"tests" \
	"Makefile" \
	"metadata.json" \
	"package.json" \
	".github" \
	".circleci" \
	"docker-compose.yml" \
	"build_readmes" \
	"build" \
	".idea" \
	"*.yml" \
	"*.phar" \
	"composer.*" \
	"vendor" \
	)
declare -a build=()
plugin_path="${short_name}"
version=$(./bin/get_plugin_version.sh "loader")
metadata="../metadata.json"
src_path="$(pwd)/"
dst_path="./build/${plugin_path}"
kit_path="./kits"
kit_name="${kit_path}/${short_name}-${version}.zip"
remote_path="./www/eighty20results.com/public_html/protected-content/"
echo "Building ${short_name} kit for version ${version}"

mkdir -p "${kit_path}"
mkdir -p "${dst_path}"

if [[ -f "${kit_name}" ]]
then
    echo "Kit is already present. Cleaning up"
    rm -rf "${dst_path}"
    rm -f "${kit_name}"
fi

for p in "${include[@]}"; do
  echo "Processing ${src_path}${p}"
  if ls "${src_path}${p}" > /dev/null 2>&1; then
    echo "Copying ${src_path}${p} to ${dst_path}"
	  cp -R "${src_path}${p}" "${dst_path}"
  fi
done

for e in "${exclude[@]}"; do
  if ls "${src_path}${e}" 1> /dev/null 2>&1; then
  	if [[ "${e}" =~ '/' ]]; then
			e=$(awk -F/ '{ print $NF }' <<< "${e}")
		fi
  	echo "Excluding ${e} from ${dst_path}"
    find "${dst_path}" -iname "${e}" -exec rm -rf {} \;
  fi
done

for b in "${build[@]}"; do
  if ls "${src_path}${b}" 1> /dev/null 2>&1; then
      cp -R "${src_path}${b}" "${dst_path}"
  fi
done

cd "${dst_path}/.." || exit 1
zip -r "${kit_name}" "${plugin_path}"
# We _want_ to expand the variables on the client side
# shellcheck disable=SC2029
ssh "${remote_server}" "cd ${remote_path}; mkdir -p \"${short_name}\""

echo "Copying ${kit_name} to ${remote_server}:${remote_path}/${short_name}/"
scp "${kit_name}" "${remote_server}:${remote_path}/${short_name}/"

echo "Copying ${metadata} to ${remote_server}:${remote_path}/${short_name}/"
scp "${metadata}" "${remote_server}:${remote_path}/${short_name}/"

echo "Linking ${short_name}/${short_name}-${version}.zip to ${short_name}.zip on remote server"
# We _want_ to expand the variables on the client side
# shellcheck disable=SC2029
ssh "${remote_server}" \
	"cd ${remote_path}/ ; ln -sf \"${short_name}\"/\"${short_name}\"-\"${version}\".zip \"${short_name}\".zip"

# Return to the root directory
cd "${src_path}" || die 1

# And clean up
rm -rf "${dst_path}"
