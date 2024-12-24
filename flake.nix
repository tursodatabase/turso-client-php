{
  inputs = {
    fenix.url = "github:nix-community/fenix";
    flake-utils.url = "github:numtide/flake-utils";
    naersk.url = "github:nix-community/naersk";
    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
  };

  outputs = { self, fenix, flake-utils, naersk, nixpkgs }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = (import nixpkgs) {
          inherit system;
        };

        toolchain = with fenix.packages.${system};
          combine [
            minimal.rustc
            minimal.cargo
            targets.x86_64-pc-windows-gnu.latest.rust-std
            targets.i686-pc-windows-gnu.latest.rust-std
          ];

        naersk' = naersk.lib.${system}.override {
          cargo = toolchain;
          rustc = toolchain;
        };

        naerskBuildPackage = target: args:
          naersk'.buildPackage (
            args
              // { CARGO_BUILD_TARGET = target; }
              // cargoConfig
          );

        cargoConfig = {
          CARGO_TARGET_X86_64_PC_WINDOWS_GNU_RUNNER = pkgs.writeScript "wine-wrapper" ''
            export WINEPREFIX="$(mktemp -d)"
            exec wine64 $@
          '';
        };

      in rec {
        defaultPackage = packages.x86_64-pc-windows-gnu;

        packages.x86_64-pc-windows-gnu = naerskBuildPackage "x86_64-pc-windows-gnu" {
          src = ./.;
          doCheck = true;
          strictDeps = true;

          depsBuildBuild = with pkgs; [
            pkgsCross.mingwW64.stdenv.cc
            pkgsCross.mingwW64.windows.pthreads
            pkgs.php81  # PHP 8.1
            pkgs.php82  # PHP 8.2
            pkgs.php83  # PHP 8.3
          ];

          nativeBuildInputs = with pkgs; [
            wineWowPackages.stable
          ];
        };

        packages.i686-pc-windows-gnu = 
          let
            cc' = pkgs.pkgsCross.mingw32.buildPackages.wrapCC (
              pkgs.pkgsCross.mingw32.buildPackages.gcc.cc.overrideAttrs (oldAttr: rec{
                configureFlags = oldAttr.configureFlags ++ [
                  "--disable-sjlj-exceptions --with-dwarf2"
                ];
              })
            );

          in naerskBuildPackage "i686-pc-windows-gnu" {
            src = ./.;
            doCheck = true;
            strictDeps = true;

            depsBuildBuild = [cc'] ++ (with pkgs.pkgsCross.mingw32.windows; [ pthreads mcfgthreads ]);
            postInstall = ''
              ln -s ${pkgs.pkgsCross.mingw32.windows.mcfgthreads}/bin/mcfgthread-12.dll $out/bin/mcfgthread-12.dll
            '';
            CARGO_TARGET_I686_PC_WINDOWS_GNU_RUSTFLAGS = "-Clink-args=-lmcfgthread";
            CARGO_TARGET_I686_PC_WINDOWS_GNU_RUNNER = pkgs.writeScript "wine-wrapper" ''
              export WINEPREFIX="$(mktemp -d)"
              ln -s \
                ${pkgs.pkgsCross.mingw32.windows.mcfgthreads}/bin/mcfgthread-12.dll \
                mcfgthread-12.dll
              exec wine64 $@
            '';

            nativeBuildInputs = with pkgs; [
              wineWowPackages.stable
            ];
          };

        devShell = pkgs.mkShell (
          {
            inputsFrom = with packages; [ x86_64-pc-windows-gnu ];
            CARGO_BUILD_TARGET = "x86_64-pc-windows-gnu";
          } // cargoConfig
        );
      }
  );
}
