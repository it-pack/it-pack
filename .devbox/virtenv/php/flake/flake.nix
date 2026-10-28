{
  description = "A flake that outputs PHP with custom extensions. Used by the devbox php plugin";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/b3da656039dc7a6240f27b2ef8cc6a3ef3bccae7";
  };

  outputs = { self, nixpkgs }:
    let
      extensions = builtins.concatLists(builtins.filter (x: x != null) [
        (builtins.match "^php.*Extensions\.([^@]*).*$" "path:/home/quill/tinker/php/project/it-pack/.devbox/virtenv/php/flake")
        (builtins.match "^php.*Extensions\.([^@]*).*$" "path:/home/quill/tinker/php/project/it-pack/.devbox/virtenv/php/flake#composer")
        (builtins.match "^php.*Extensions\.([^@]*).*$" "php@8.5")
      ]);

      php = nixpkgs.legacyPackages.x86_64-linux.php85.withExtensions (
        { enabled, all }: enabled ++ (with all; 
          map (ext: all.${ext}) extensions
        )
      );
    in
    {
      packages.x86_64-linux = {
        default = php;
        composer = php.packages.composer;
      };
    };
}
