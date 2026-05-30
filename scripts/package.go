package main

import (
	"archive/zip"
	"bytes"
	"crypto/sha1"
	"encoding/hex"
	"errors"
	"flag"
	"fmt"
	"io"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"runtime"
	"sort"
	"strings"
	"time"
)

var (
	projectRoot string
	outDir      string
	zipName     string
	workDir     string
	sourceBuild bool
	keepWork    bool
	skipBuild   bool
)

func main() {
	defaultRoot, err := findProjectRoot()
	must(err)

	flag.StringVar(&projectRoot, "root", defaultRoot, "MMUI-project root")
	flag.StringVar(&outDir, "out", filepath.Join(defaultRoot, "release"), "release output directory")
	flag.StringVar(&zipName, "name", "", "output zip file name")
	flag.StringVar(&workDir, "work", "", "temporary work parent directory")
	flag.BoolVar(&sourceBuild, "source-build", false, "build frontend projects in their source directories")
	flag.BoolVar(&keepWork, "keep-work", false, "keep temporary package directory")
	flag.BoolVar(&skipBuild, "skip-build", false, "skip npm builds and package existing dist directories")
	flag.Parse()

	projectRoot, err = filepath.Abs(projectRoot)
	must(err)
	outDir, err = filepath.Abs(outDir)
	must(err)

	ecsRoot := filepath.Join(projectRoot, "MMUI-V2X")
	loginRoot := filepath.Join(projectRoot, "LoginUI")
	overrideRoot := filepath.Join(projectRoot, "qz-override")
	requireDir(ecsRoot)
	requireDir(loginRoot)
	requireDir(overrideRoot)

	if strings.TrimSpace(workDir) != "" {
		workDir, err = filepath.Abs(workDir)
		must(err)
		must(os.MkdirAll(workDir, 0755))
	}

	workRoot, err := os.MkdirTemp(workDir, "mmui-qz-package-*")
	must(err)
	if keepWork {
		fmt.Println("work:", workRoot)
	}

	stageRoot := filepath.Join(workRoot, "qzsystem")
	buildEcsRoot := filepath.Join(workRoot, "build", "MMUI-V2X")
	buildLoginRoot := filepath.Join(workRoot, "build", "LoginUI")
	buildEcsDist := filepath.Join(buildEcsRoot, "dist-package")
	buildLoginDist := filepath.Join(buildLoginRoot, "dist-package")
	if sourceBuild {
		buildEcsRoot = ecsRoot
		buildLoginRoot = loginRoot
		buildEcsDist = filepath.Join(ecsRoot, ".mmui-package-dist")
		buildLoginDist = filepath.Join(loginRoot, ".mmui-package-dist")
		must(os.RemoveAll(buildEcsDist))
		must(os.RemoveAll(buildLoginDist))
	}
	ecsDist := filepath.Join(stageRoot, "public", "src", "static", "mmui")
	loginDist := filepath.Join(stageRoot, "public", "static", "component", "auroraboat", "login")
	ecsBundle := filepath.Join(stageRoot, "view", "control", "ecs", "mmui_bundle.html")
	loginBundle := filepath.Join(stageRoot, "view", "index", "index", "mmui_bundle.html")

	fmt.Println("copy override")
	must(copyTree(overrideRoot, stageRoot, nil))

	if !skipBuild {
		if sourceBuild {
			fmt.Println("use frontend source directories")
		} else {
			fmt.Println("copy frontend sources")
			must(copyTree(ecsRoot, buildEcsRoot, skipFrontendTransient))
			must(copyTree(loginRoot, buildLoginRoot, skipFrontendTransient))
		}
		must(ensureNodeModules(buildEcsRoot))
		must(ensureNodeModules(buildLoginRoot))

		fmt.Println("build ECS")
		must(run(buildEcsRoot, map[string]string{
			"MMUI_ECS_DIST_DIR":    buildEcsDist,
			"MMUI_ECS_BUNDLE_PATH": ecsBundle,
		}, "npm", "run", "build:php"))
		must(copyTree(buildEcsDist, ecsDist, nil))

		fmt.Println("build LoginUI")
		must(run(buildLoginRoot, map[string]string{
			"MMUI_LOGIN_DIST_DIR": buildLoginDist,
			"VITE_ASSET_BASE":     "/static/component/auroraboat/login/",
		}, "npm", "run", "build"))
		must(copyTree(buildLoginDist, loginDist, nil))
	} else {
		fmt.Println("copy existing ECS dist")
		must(copyTree(filepath.Join(ecsRoot, "dist"), ecsDist, nil))
		fmt.Println("copy existing LoginUI dist")
		must(copyTree(filepath.Join(loginRoot, "dist"), loginDist, nil))
	}

	must(writeLoginBundle(filepath.Join(loginDist, "index.html"), loginBundle))
	must(writePackageReadme(stageRoot))

	must(os.MkdirAll(outDir, 0755))
	if strings.TrimSpace(zipName) == "" {
		stamp := time.Now().Format("20060102-150405")
		zipName = "mmui-v2x-qz-override-" + stamp + ".zip"
	}
	if filepath.Ext(zipName) == "" {
		zipName += ".zip"
	}
	zipPath := filepath.Join(outDir, filepath.Base(zipName))
	must(zipDir(stageRoot, zipPath))

	sum, err := fileSHA1(zipPath)
	must(err)

	if !keepWork {
		if sourceBuild {
			fmt.Println("cleanup source build outputs")
			must(os.RemoveAll(buildEcsDist))
			must(os.RemoveAll(buildLoginDist))
		}
		fmt.Println("cleanup work directory")
		must(os.RemoveAll(workRoot))
	}

	fmt.Println("zip:", zipPath)
	fmt.Println("sha1:", sum)
}

func skipFrontendTransient(path string, entry fs.DirEntry) bool {
	name := entry.Name()
	if entry.IsDir() {
		switch name {
		case "node_modules", "dist", "dist-ssr", ".mmui-package-dist", ".git", ".idea", ".vscode", ".vite":
			return true
		}
	}
	switch name {
	case ".vite-dev.err.log", ".vite-dev.out.log", "vite-css-server-art.err.log":
		return true
	}
	return false
}

func ensureNodeModules(root string) error {
	if info, err := os.Stat(filepath.Join(root, "node_modules")); err == nil && info.IsDir() {
		return nil
	}
	if _, err := os.Stat(filepath.Join(root, "package-lock.json")); err == nil {
		fmt.Println("npm ci:", root)
		return run(root, nil, "npm", "ci")
	}
	fmt.Println("npm install:", root)
	return run(root, nil, "npm", "install")
}

func findProjectRoot() (string, error) {
	_, file, _, ok := runtime.Caller(0)
	if !ok {
		return "", errors.New("cannot locate current file")
	}
	return filepath.Abs(filepath.Join(filepath.Dir(file), ".."))
}

func requireDir(path string) {
	info, err := os.Stat(path)
	if err != nil {
		must(fmt.Errorf("missing directory: %s", path))
	}
	if !info.IsDir() {
		must(fmt.Errorf("not a directory: %s", path))
	}
}

func run(dir string, env map[string]string, name string, args ...string) error {
	cmd := exec.Command(name, args...)
	cmd.Dir = dir
	cmd.Env = append(os.Environ(), envList(env)...)
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	return cmd.Run()
}

func envList(env map[string]string) []string {
	keys := make([]string, 0, len(env))
	for key := range env {
		keys = append(keys, key)
	}
	sort.Strings(keys)
	out := make([]string, 0, len(keys))
	for _, key := range keys {
		out = append(out, key+"="+env[key])
	}
	return out
}

func copyTree(src, dst string, skip func(string, fs.DirEntry) bool) error {
	src, err := filepath.Abs(src)
	if err != nil {
		return err
	}
	dst, err = filepath.Abs(dst)
	if err != nil {
		return err
	}
	return filepath.WalkDir(src, func(path string, entry fs.DirEntry, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		if path == src {
			return nil
		}
		if skip != nil && skip(path, entry) {
			if entry.IsDir() {
				return filepath.SkipDir
			}
			return nil
		}
		rel, err := filepath.Rel(src, path)
		if err != nil {
			return err
		}
		target := filepath.Join(dst, rel)
		if entry.IsDir() {
			return os.MkdirAll(target, 0755)
		}
		info, err := entry.Info()
		if err != nil {
			return err
		}
		return copyFile(path, target, info.Mode())
	})
}

func copyFile(src, dst string, mode fs.FileMode) error {
	if err := os.MkdirAll(filepath.Dir(dst), 0755); err != nil {
		return err
	}
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()
	out, err := os.OpenFile(dst, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, mode)
	if err != nil {
		return err
	}
	if _, err := io.Copy(out, in); err != nil {
		out.Close()
		return err
	}
	return out.Close()
}

func writeLoginBundle(distIndex, bundlePath string) error {
	source, err := os.ReadFile(distIndex)
	if err != nil {
		return err
	}
	body := string(source)
	body = strings.Replace(body, "<!DOCTYPE html>", "<!doctype html>", 1)
	body = injectLoginHead(body)
	body = strings.Replace(body, "<body>", "<body>\n    <script>\n      window.__MMUI_LOGIN_CONFIG__ = <?php echo json_encode($mmuiLoginConfigForJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;\n    </script>", 1)
	body = strings.Replace(body, "</body>", loginBrandScript()+"\n  </body>", 1)

	output := `<?php
$mmuiLoginConfig = $mmuiLoginConfig ?? [];
$mmuiLoginTitle = trim((string)($mmuiLoginConfig['title'] ?? '轻舟云（QZSYSTEM）'));
$mmuiLoginLogo = trim((string)($mmuiLoginConfig['logo'] ?? ''));
$mmuiLoginIcon = $mmuiLoginLogo !== '' ? $mmuiLoginLogo : '/static/component/auroraboat/login/favicon.ico';
$mmuiLoginConfigForJson = $mmuiLoginConfig;
?>
` + body

	if err := os.MkdirAll(filepath.Dir(bundlePath), 0755); err != nil {
		return err
	}
	return os.WriteFile(bundlePath, []byte(output), 0644)
}

func injectLoginHead(input string) string {
	titleRe := regexp.MustCompile(`(?is)<title>.*?</title>`)
	input = titleRe.ReplaceAllStringFunc(input, func(string) string {
		return `<title><?php echo htmlspecialchars($mmuiLoginTitle, ENT_QUOTES, 'UTF-8'); ?></title>`
	})
	iconRe := regexp.MustCompile(`(?is)<link\s+rel="icon"[^>]*>`)
	input = iconRe.ReplaceAllStringFunc(input, func(string) string {
		return `<link rel="icon" href="<?php echo htmlspecialchars($mmuiLoginIcon, ENT_QUOTES, 'UTF-8'); ?>">`
	})
	noCache := `    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">`
	return strings.Replace(input, `    <meta charset="UTF-8">`, `    <meta charset="UTF-8">
`+noCache, 1)
}

func loginBrandScript() string {
	return `    <script>
      (function () {
        var config = window.__MMUI_LOGIN_CONFIG__ || {};
        var title = String(config.title || '').trim();
        var logo = String(config.logo || '').trim();
        var doneTitle = !title;
        var doneLogo = !logo;

        function applyBrand() {
          if (title) {
            if (document.title !== title) {
              document.title = title;
            }
            var subTitle = document.querySelector('.card-top h3');
            if (subTitle && subTitle.textContent !== title) {
              subTitle.textContent = title;
            }
            doneTitle = !!subTitle || document.title === title;
          }
          if (logo) {
            var img = document.querySelector('.card-logo img.logo');
            if (img && img.getAttribute('src') !== logo) {
              img.setAttribute('src', logo);
            }
            doneLogo = !!img;
          }
        }

        var attempts = 0;
        var timer = window.setInterval(function () {
          attempts += 1;
          applyBrand();
          if ((doneTitle && doneLogo) || attempts >= 40) {
            window.clearInterval(timer);
          }
        }, 100);
        window.addEventListener('load', applyBrand);
        applyBrand();
      })();
    </script>`
}

func writePackageReadme(stageRoot string) error {
	text := `# MMUI-V2X Qz Override

Copy the contents of this package to the qzsystem root.

This package contains:
- MMUI qz hooks and bridge files
- ECS MMUI built assets under public/src/static/mmui
- LoginUI built assets under public/static/component/auroraboat/login

It intentionally excludes .env, install.lock, demo SQL, and local debug files.
`
	return os.WriteFile(filepath.Join(stageRoot, "MMUI_PACKAGE_README.md"), []byte(text), 0644)
}

func zipDir(src, zipPath string) error {
	var files []string
	if err := filepath.WalkDir(src, func(path string, entry fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		if entry.IsDir() {
			return nil
		}
		files = append(files, path)
		return nil
	}); err != nil {
		return err
	}
	sort.Strings(files)

	out, err := os.Create(zipPath)
	if err != nil {
		return err
	}
	defer out.Close()
	zw := zip.NewWriter(out)
	defer zw.Close()

	for _, path := range files {
		rel, err := filepath.Rel(src, path)
		if err != nil {
			return err
		}
		rel = filepath.ToSlash(rel)
		info, err := os.Stat(path)
		if err != nil {
			return err
		}
		header, err := zip.FileInfoHeader(info)
		if err != nil {
			return err
		}
		header.Name = rel
		header.Method = zip.Deflate
		writer, err := zw.CreateHeader(header)
		if err != nil {
			return err
		}
		in, err := os.Open(path)
		if err != nil {
			return err
		}
		_, copyErr := io.Copy(writer, in)
		closeErr := in.Close()
		if copyErr != nil {
			return copyErr
		}
		if closeErr != nil {
			return closeErr
		}
	}
	return nil
}

func fileSHA1(path string) (string, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return "", err
	}
	sum := sha1.Sum(data)
	return hex.EncodeToString(sum[:]), nil
}

func must(err error) {
	if err != nil {
		var buf bytes.Buffer
		fmt.Fprintf(&buf, "error: %v\n", err)
		os.Stderr.Write(buf.Bytes())
		os.Exit(1)
	}
}
